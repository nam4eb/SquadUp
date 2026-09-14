param(
    [int]$Samples = 5,
    [string]$Email = 'demo@squadup.test',
    [string]$Password = $env:BENCHMARK_PASSWORD,
    [string]$OutputPath = 'docs/performance/local-authenticated-2026-09-14.json'
)
$ErrorActionPreference = 'Stop'
if (!$Password) { throw 'Set BENCHMARK_PASSWORD before running this local measurement.' }
if ($Samples -lt 1 -or $Samples -gt 10) { throw 'Samples must be between 1 and 10.' }
$fastBase = 'http://127.0.0.1:8001/api/v1'
$login = Invoke-RestMethod -Method Post -Uri "$fastBase/auth/login" -ContentType 'application/json' -Body (@{
    email = $Email; password = $Password; device_name = 'local-latency-check'
} | ConvertTo-Json)
$headers = @{ Authorization = "Bearer $($login.data.token)"; Accept = 'application/json' }
$measurements = @()
try {
    foreach ($port in @(8000, 8001)) {
        foreach ($endpoint in @('auth/me', 'explore', 'conversations')) {
            $uri = "http://127.0.0.1:$port/api/v1/$endpoint"
            $null = Invoke-WebRequest $uri -Headers $headers -TimeoutSec 30
            $durations = @()
            for ($sample = 0; $sample -lt $Samples; $sample++) {
                $watch = [Diagnostics.Stopwatch]::StartNew()
                $response = Invoke-WebRequest $uri -Headers $headers -TimeoutSec 30
                $watch.Stop()
                $durations += [Math]::Round($watch.Elapsed.TotalMilliseconds, 2)
                if ($response.StatusCode -ne 200) { throw "Unexpected status for $endpoint" }
            }
            $sorted = @($durations | Sort-Object)
            $measurements += [pscustomobject]@{
                port = $port; endpoint = $endpoint; samples = $Samples
                median_ms = $sorted[[int][Math]::Floor($Samples / 2)]
                min_ms = $sorted[0]; max_ms = $sorted[-1]; durations_ms = $durations
            }
            Write-Host "$port $endpoint median=$($measurements[-1].median_ms) ms"
        }
    }
    [pscustomobject]@{
        measured_at = [DateTime]::UtcNow.ToString('o')
        workload = 'Sequential authenticated local requests, one warm-up per endpoint; not a load/capacity test.'
        measurements = $measurements
    } | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $OutputPath -Encoding utf8
} finally {
    $null = Invoke-RestMethod -Method Post -Uri "$fastBase/auth/logout" -Headers $headers -TimeoutSec 30
}
