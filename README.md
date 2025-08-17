# Drupal AI Search with Chat Interface

A Drupal-based AI search system that combines Search API with a Python-powered chat interface using Google Gemini.

## Quick Setup Guide

### Prerequisites
- **Drupal**: DDEV or similar local development environment
- **Python**: Python 3.8+ installed
- **API Key**: Google Gemini API key from [Google AI Studio](https://makersuite.google.com/app/apikey)

### 1. Drupal Development Environment

```bash
# Start Drupal with DDEV
cd drupal
ddev start

# Install dependencies if needed
ddev composer install

# Access your site
# URL: https://drupal-ai-search.ddev.site (or your configured domain)
```

**Key Drupal modules enabled:**
- `ai_chat` (custom module for chat interface)
- `search_api_query` (for content search)
- Search API with your vector database setup

### 2. Python Chat Service

```bash
# Navigate to Python directory
cd python

# Manual setup (alternative):
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
cp .env.example .env
# Edit .env and add your GEMINI_API_KEY
python main.py
```

**Configure `.env`:**
```env
GEMINI_API_KEY=your_gemini_api_key_here
GEMINI_MODEL=gemini-2.5-flash
FASTAPI_HOST=127.0.0.1
FASTAPI_PORT=8001
```

### 3. Test the Integration

1. **Start both services:**
   - Drupal: `ddev start` (typically runs on port 8448)
   - Python: `./run.sh` (runs on port 8001)

2. **Access chat interface:**
   - Navigate to: `https://your-drupal-site.ddev.site/chat`
   - Login to Drupal first

3. **Verify services:**
   - Python health check: http://127.0.0.1:8001/health
   - Chat API: http://127.0.0.1:8001/docs (FastAPI docs)

## How It Works

```
User Message → Drupal Chat → Search API → Python FastAPI → Google Gemini → Response
```

1. **User asks question** → Drupal chat interface (`/chat`)
2. **Drupal searches content** → Search API finds relevant content
3. **Drupal calls Python API** → POST to `http://127.0.0.1:8001/chat`
4. **Python calls Gemini** → With search context + conversation history
5. **AI generates response** → Based on your content + chat context
6. **Response flows back** → Python → Drupal → User interface

## Development Workflow

### Daily Development
```bash
# Terminal 1: Start Drupal
cd drupal && ddev start

# Terminal 2: Start Python service
cd python && ./run.sh

# Your chat interface is ready at /chat
```

### Making Changes

**Drupal changes:**
- Edit files in `drupal/web/modules/custom/ai_chat/`
- Clear cache: `ddev drush cr`

**Python changes:**
- Edit `python/main.py`
- Service auto-reloads with `reload=True`

### Debugging

**Drupal logs:**
```bash
ddev drush watchdog:show --filter=ai_chat
# Or check /admin/reports/dblog
```

**Python logs:**
- View in terminal where `./run.sh` is running
- Logs show requests/responses and errors

## Project Structure

```
drupal-ai-search/
├── drupal/                 # Drupal application
│   └── web/modules/custom/ai_chat/  # Chat module
├── python/                 # Python FastAPI service
│   ├── main.py            # Main FastAPI application
│   ├── requirements.txt   # Python dependencies
│   ├── run.sh            # Setup and run script
│   └── .env              # Environment configuration
└── README.md             # This file
```

## Configuration Files

### Key Drupal Files
- `drupal/web/modules/custom/ai_chat/src/Controller/ChatController.php` - Main chat logic
- `drupal/web/modules/custom/ai_chat/ai_chat.routing.yml` - Routes
- `drupal/web/modules/custom/ai_chat/ai_chat.libraries.yml` - Frontend assets

### Key Python Files
- `python/main.py` - FastAPI server with chat endpoint
- `python/.env` - Environment variables (API keys, etc.)
- `python/requirements.txt` - Python dependencies

## Troubleshooting

### Common Issues

**"Connection refused" error:**
- Ensure Python service is running: `cd python && ./run.sh`
- Check if port 8001 is available: `lsof -i :8001`

**"API key not configured":**
- Verify `.env` file exists in `python/` directory
- Ensure `GEMINI_API_KEY` is set correctly

**Drupal chat page shows login required:**
- Make sure you're logged into Drupal
- Check user permissions for chat access

**Search results not appearing:**
- Verify Search API is configured and indexed
- Check if `search_api_query` service is available

### Development Tips

1. **Use browser dev tools** to monitor API calls to `/api/chat`
2. **Check Python logs** for detailed request/response information
3. **Use Drupal's dblog** (`/admin/reports/dblog`) for server-side debugging
4. **Test Python API directly** using http://127.0.0.1:8001/docs

## Production Considerations

- **Security**: Update CORS settings in `python/main.py`
- **Environment**: Use proper environment variables for production
- **Monitoring**: Add proper logging and error tracking
- **Scale**: Consider using Docker containers for deployment