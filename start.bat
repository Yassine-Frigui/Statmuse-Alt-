curl https://api.groq.com/openai/v1/chat/completions -s \
-H "Content-Type: application/json" \
-H "Authorization: Bearer GROQ_API_KEY_REMOVED" \
-d '{
"model": "openai/gpt-oss-120b",
"messages": [{
    "role": "user",
    "content": "Please briefly explain the importance of fast AI inference."
}]
}'
