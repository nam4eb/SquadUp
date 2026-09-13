param(
    [string]$DeviceId = ""
)

$arguments = @(
    "run",
    "--dart-define=API_BASE_URL=http://10.0.2.2:8001/api/v1"
)

if ($DeviceId) {
    $arguments += @("-d", $DeviceId)
}

& flutter @arguments
exit $LASTEXITCODE
