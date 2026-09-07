param(
    [string]$BaseUrl = "http://127.0.0.1:8000/api/v1",
    [string]$Email = "demo@squadup.test",
    [Parameter(Mandatory = $true)][string]$Password,
    [int]$Warmup = 3,
    [int]$Requests = 20,
    [string]$OutputPath
)

$ErrorActionPreference = "Stop"
$client = [System.Net.Http.HttpClient]::new()
$client.Timeout = [TimeSpan]::FromSeconds(20)
$client.DefaultRequestHeaders.Accept.ParseAdd("application/json")

function Invoke-TimedRequest {
    param([string]$Method, [string]$Url, [string]$Body = $null)
    $request = [System.Net.Http.HttpRequestMessage]::new($Method, $Url)
    if ($Body) {
        $request.Content = [System.Net.Http.StringContent]::new($Body, [Text.Encoding]::UTF8, "application/json")
    }
    $watch = [Diagnostics.Stopwatch]::StartNew()
    $response = $null
    try {
        $response = $client.Send($request)
        $content = $response.Content.ReadAsStringAsync().GetAwaiter().GetResult()
        $watch.Stop()
        $queryCount = if ($response.Headers.Contains('X-DB-Query-Count')) { [int]($response.Headers.GetValues('X-DB-Query-Count') | Select-Object -First 1) } else { $null }
        $serverTiming = if ($response.Headers.Contains('Server-Timing')) { ($response.Headers.GetValues('Server-Timing') | Select-Object -First 1) } else { $null }
        $databaseMs = if ($serverTiming -match 'db;dur=([0-9.]+)') { [double]$Matches[1] } else { $null }
        $applicationMs = if ($serverTiming -match 'app;dur=([0-9.]+)') { [double]$Matches[1] } else { $null }
        [pscustomobject]@{ Status = [int]$response.StatusCode; DurationMs = $watch.Elapsed.TotalMilliseconds; Body = $content; QueryCount = $queryCount; ServerTiming = $serverTiming; DatabaseMs = $databaseMs; ApplicationMs = $applicationMs }
    } catch {
        $watch.Stop()
        [pscustomobject]@{ Status = 0; DurationMs = $watch.Elapsed.TotalMilliseconds; Body = $_.Exception.Message }
    } finally {
        if ($response) { $response.Dispose() }
        $request.Dispose()
    }
}

function Get-Percentile {
    param([double[]]$Values, [double]$Percentile)
    $sorted = $Values | Sort-Object
    $index = [Math]::Ceiling($Percentile * $sorted.Count) - 1
    $sorted[[Math]::Max(0, $index)]
}

$loginBody = @{ email = $Email; password = $Password; device_name = "phase-0-benchmark" } | ConvertTo-Json
$login = Invoke-TimedRequest -Method POST -Url "$BaseUrl/auth/login" -Body $loginBody
if ($login.Status -ne 200) { throw "Login failed with HTTP $($login.Status): $($login.Body)" }
$loginPayload = $login.Body | ConvertFrom-Json
$token = $loginPayload.data.token
if ([string]::IsNullOrWhiteSpace($token)) { throw "Login succeeded but data.token is missing." }
$client.DefaultRequestHeaders.Authorization = [Net.Http.Headers.AuthenticationHeaderValue]::new("Bearer", $token)

$targets = @(
    @{ Name = "activities"; Url = "$BaseUrl/activities" },
    @{ Name = "stories"; Url = "$BaseUrl/stories" },
    @{ Name = "conversations"; Url = "$BaseUrl/conversations" },
    @{ Name = "presence"; Url = "$BaseUrl/presence/heartbeat"; Method = "POST"; Body = '{"status":"online"}' }
)

$report = @()
foreach ($target in $targets) {
    $method = if ($target.Method) { $target.Method } else { "GET" }
    1..$Warmup | ForEach-Object { $null = Invoke-TimedRequest -Method $method -Url $target.Url -Body $target.Body }
    $samples = 1..$Requests | ForEach-Object { Invoke-TimedRequest -Method $method -Url $target.Url -Body $target.Body }
    $durations = [double[]]($samples | ForEach-Object DurationMs)
    $errors = @($samples | Where-Object { $_.Status -lt 200 -or $_.Status -ge 300 }).Count
    $statusCodes = ($samples | Group-Object Status | Sort-Object Name | ForEach-Object { "$($_.Name):$($_.Count)" }) -join ","
    $firstError = $samples | Where-Object { $_.Status -lt 200 -or $_.Status -ge 300 } | Select-Object -First 1
    $queryCounts = @($samples | Where-Object { $null -ne $_.QueryCount } | ForEach-Object QueryCount)
    $databaseDurations = [double[]]@($samples | Where-Object { $null -ne $_.DatabaseMs } | ForEach-Object DatabaseMs)
    $applicationDurations = [double[]]@($samples | Where-Object { $null -ne $_.ApplicationMs } | ForEach-Object ApplicationMs)
    $report += [pscustomobject]@{
        Endpoint = $target.Name
        Requests = $Requests
        Errors = $errors
        StatusCodes = $statusCodes
        FirstError = if ($firstError) { $firstError.Body.Substring(0, [Math]::Min(300, $firstError.Body.Length)) } else { $null }
        QueryCount = if ($queryCounts.Count -gt 0) { [Math]::Round(($queryCounts | Measure-Object -Average).Average, 2) } else { $null }
        DatabaseAvgMs = if ($databaseDurations.Count -gt 0) { [Math]::Round(($databaseDurations | Measure-Object -Average).Average, 2) } else { $null }
        DatabaseP95Ms = if ($databaseDurations.Count -gt 0) { [Math]::Round((Get-Percentile $databaseDurations 0.95), 2) } else { $null }
        ApplicationAvgMs = if ($applicationDurations.Count -gt 0) { [Math]::Round(($applicationDurations | Measure-Object -Average).Average, 2) } else { $null }
        ApplicationP95Ms = if ($applicationDurations.Count -gt 0) { [Math]::Round((Get-Percentile $applicationDurations 0.95), 2) } else { $null }
        MinMs = [Math]::Round(($durations | Measure-Object -Minimum).Minimum, 2)
        P50Ms = [Math]::Round((Get-Percentile $durations 0.50), 2)
        P95Ms = [Math]::Round((Get-Percentile $durations 0.95), 2)
        MaxMs = [Math]::Round(($durations | Measure-Object -Maximum).Maximum, 2)
    }
}

$report | Format-Table -AutoSize
if ($OutputPath) {
    $report | ConvertTo-Json | Set-Content -LiteralPath $OutputPath -Encoding UTF8
    Write-Host "Saved report to $OutputPath"
}

$null = Invoke-TimedRequest -Method POST -Url "$BaseUrl/auth/logout"
$client.Dispose()
