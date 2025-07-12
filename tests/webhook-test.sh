#!/bin/bash

# Base URL for the webhook
BASE_URL="http://localhost:8000/api/webhook/whatsapp"

# Test 1: Simple text message
echo "Test 1: Sending text message..."
curl -X POST $BASE_URL \
  -H "Content-Type: application/json" \
  -d '{
    "number": "+1234567890",
    "name": "John Doe",
    "message": "Hello, I need help with my order",
    "message_type": "text"
  }'
echo -e "\n\n"

# Test 2: Message with image
echo "Test 2: Sending message with image..."
curl -X POST $BASE_URL \
  -H "Content-Type: application/json" \
  -d '{
    "number": "+1234567890",
    "name": "John Doe",
    "message": "Here is my receipt",
    "message_type": "image",
    "media_url": "https://example.com/image.jpg"
  }'
echo -e "\n\n"

# Test 3: New customer
echo "Test 3: New customer message..."
curl -X POST $BASE_URL \
  -H "Content-Type: application/json" \
  -d '{
    "number": "+9876543210",
    "name": "Jane Smith",
    "message": "Hi, is anyone available?",
    "message_type": "text"
  }'
echo -e "\n\n"

# Test 4: Follow-up message from first customer
echo "Test 4: Follow-up message..."
curl -X POST $BASE_URL \
  -H "Content-Type: application/json" \
  -d '{
    "number": "+1234567890",
    "name": "John Doe",
    "message": "Any update on my order?",
    "message_type": "text"
  }'
echo -e "\n\n" 