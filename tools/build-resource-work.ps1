# Deployment-only resizing of generated transparent sprites; originals remain intact.
param([string]$SourceDirectory = 'C:/Users/svenm/.codex/generated_images/01a0c4e7-308c-7a02-896f-2d335be89ca2')
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing
$destination = Join-Path $PSScriptRoot '../assets/art/fantasy-village-v1/work-v1'
New-Item -ItemType Directory -Force -Path $destination | Out-Null
$assets = @(
    @('d6c828af-0dca-4397-8934-069ddbd3c4e5', 'gold-empty', 384),
    @('35b84a1c-aab0-4f0c-b541-a36241f527d6', 'gold-cart', 256),
    @('fe19806e-3c79-4d9b-8425-70ed7004dacc', 'quarry-empty', 384),
    @('990c98d6-a8ef-4bdd-bc4b-caf0d2ef32c2', 'quarry-load', 256),
    @('271399d7-be0b-460f-8bf2-9544c17de5cb', 'lumber-worker', 768),
    @('1044fb0a-0b43-41fd-86e7-67ef7c75fd19', 'farm-worker', 768)
)
foreach ($asset in $assets) {
    $source = [System.Drawing.Image]::FromFile((Join-Path $SourceDirectory ('exec-' + $asset[0] + '.png')))
    $bitmap = [System.Drawing.Bitmap]::new($asset[2], $asset[2], [System.Drawing.Imaging.PixelFormat]::Format32bppArgb)
    $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
    try {
        $graphics.CompositingMode = [System.Drawing.Drawing2D.CompositingMode]::SourceCopy
        $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
        $graphics.DrawImage($source, 0, 0, $asset[2], $asset[2])
        $bitmap.Save((Join-Path $destination ($asset[1] + '.png')), [System.Drawing.Imaging.ImageFormat]::Png)
    } finally { $graphics.Dispose(); $bitmap.Dispose(); $source.Dispose() }
}
Get-ChildItem -LiteralPath $destination | Select-Object Name, Length
