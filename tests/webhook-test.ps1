# Base URL for the webhook (update with your tenant domain)
$BASE_URL = "http://tenant1.localhost:8000/api/webhook/whatsapp"

# Test 1: Simple text message
Write-Host "Test 1: Sending text message..."
$body1 = @{
    number = "+1234567890"
    name = "John Doe"
    message = "Hello, I need help with my order"
    message_type = "text"
} | ConvertTo-Json

try {
    Invoke-RestMethod -Uri $BASE_URL -Method Post -Body $body1 -ContentType "application/json"
} catch {
    Write-Host "Error response:"
    Write-Host $_.Exception.Response.GetResponseStream()
    $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
    $reader.BaseStream.Position = 0
    $reader.DiscardBufferedData()
    $responseBody = $reader.ReadToEnd()
    Write-Host $responseBody
}
Write-Host "`n"

# Test 2: Message with image
Write-Host "Test 2: Sending message with image..."
$body2 = @{
    number = "+1234567890"
    name = "John Doe"
    message = "Here is my receipt"
    message_type = "image"
    media_url = "https://example.com/image.jpg"
} | ConvertTo-Json

try {
    Invoke-RestMethod -Uri $BASE_URL -Method Post -Body $body2 -ContentType "application/json"
} catch {
    Write-Host "Error response:"
    Write-Host $_.Exception.Response.GetResponseStream()
    $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
    $reader.BaseStream.Position = 0
    $reader.DiscardBufferedData()
    $responseBody = $reader.ReadToEnd()
    Write-Host $responseBody
}
Write-Host "`n"

# Test 3: New customer
Write-Host "Test 3: New customer message..."
$body3 = @{
    number = "+9876543210"
    name = "Jane Smith"
    message = "Hi, is anyone available?"
    message_type = "text"
} | ConvertTo-Json

try {
    Invoke-RestMethod -Uri $BASE_URL -Method Post -Body $body3 -ContentType "application/json"
} catch {
    Write-Host "Error response:"
    Write-Host $_.Exception.Response.GetResponseStream()
    $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
    $reader.BaseStream.Position = 0
    $reader.DiscardBufferedData()
    $responseBody = $reader.ReadToEnd()
    Write-Host $responseBody
}
Write-Host "`n"

# Test 4: Follow-up message from first customer
Write-Host "Test 4: Follow-up message..."
$body4 = @{
    number = "+1234567890"
    name = "John Doe"
    message = "Any update on my order?"
    message_type = "text"
} | ConvertTo-Json

try {
    Invoke-RestMethod -Uri $BASE_URL -Method Post -Body $body4 -ContentType "application/json"
} catch {
    Write-Host "Error response:"
    Write-Host $_.Exception.Response.GetResponseStream()
    $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
    $reader.BaseStream.Position = 0
    $reader.DiscardBufferedData()
    $responseBody = $reader.ReadToEnd()
    Write-Host $responseBody
}
Write-Host "`n" 