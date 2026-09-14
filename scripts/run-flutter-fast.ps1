param(
    [string]$DeviceId = "",
    [string]$ApiHost = "",
    [int]$ReverbPort = 8080
)

if (!$ApiHost) {
    $ApiHost = if ($DeviceId -in @('chrome', 'edge', 'web-server', 'windows')) { '127.0.0.1' } else { '10.0.2.2' }
}

$arguments = @(
    "run",
    "--dart-define=API_BASE_URL=http://${ApiHost}:8001/api/v1",
    "--dart-define=BROADCAST_AUTH_URL=http://${ApiHost}:8001/api/broadcasting/auth",
    "--dart-define=REVERB_HOST=$ApiHost",
    "--dart-define=REVERB_PORT=$ReverbPort"
)

if ($DeviceId) {
    $arguments += @("-d", $DeviceId)
}

$flutterCommand = Get-Command flutter -ErrorAction SilentlyContinue
$flutterPath = if ($flutterCommand) { $flutterCommand.Source } else { Join-Path $env:USERPROFILE 'flutter\bin\flutter.bat' }
& $flutterPath @arguments
exit $LASTEXITCODE
