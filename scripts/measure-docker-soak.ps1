param(
    [Parameter(Mandatory = $true)][string]$Container,
    [int]$DurationSeconds = 300,
    [int]$IntervalSeconds = 5,
    [Parameter(Mandatory = $true)][string]$OutputPath
)

$ErrorActionPreference = 'Stop'
$samples = [System.Collections.Generic.List[object]]::new()
$startedAt = [DateTimeOffset]::UtcNow

while (([DateTimeOffset]::UtcNow - $startedAt).TotalSeconds -lt $DurationSeconds) {
    $raw = docker stats --no-stream --format '{{json .}}' $Container
    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($raw)) {
        throw "Unable to read Docker statistics for $Container."
    }

    $stats = $raw | ConvertFrom-Json
    $inspect = docker inspect --format '{{.RestartCount}}' $Container
    if ($LASTEXITCODE -ne 0) {
        throw "Unable to inspect $Container."
    }

    $samples.Add([pscustomobject]@{
        timestamp = [DateTimeOffset]::UtcNow.ToString('o')
        cpu_percent = [double]($stats.CPUPerc -replace '%', '')
        memory_usage = $stats.MemUsage
        memory_percent = [double]($stats.MemPerc -replace '%', '')
        network_io = $stats.NetIO
        block_io = $stats.BlockIO
        pids = [int]$stats.PIDs
        restart_count = [int]$inspect
    })

    Start-Sleep -Seconds $IntervalSeconds
}

$result = [pscustomobject]@{
    container = $Container
    started_at = $startedAt.ToString('o')
    finished_at = [DateTimeOffset]::UtcNow.ToString('o')
    duration_seconds = $DurationSeconds
    interval_seconds = $IntervalSeconds
    samples = $samples
}

$result | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $OutputPath -Encoding UTF8
Write-Host "Saved $($samples.Count) samples to $OutputPath"
