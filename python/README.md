# AI Chat Service

A FastAPI-based AI chat service that integrates with Drupal for intelligent content search and responses using Google Gemini.

## Setup

### Option 1: Quick Setup (Recommended)
```bash
cd python
./run.sh
```

### Option 2: Manual Setup
```bash
cd python
python3 -m venv venv
source venv/bin/activate  # On Windows: venv\Scripts\activate
pip install --upgrade pip
pip install -r requirements.txt
cp .env.example .env
# Edit .env and add your GEMINI_API_KEY
python main.py
```

### Option 3: Python Setup Script
```bash
cd python
python3 setup.py
venv/bin/python main.py
```

### Troubleshooting Setup

**Python 3.13 compatibility issues or "Failed to build wheels":**

1. **Clean install:**
   ```bash
   rm -rf venv
   ./run.sh
   ```

2. **Try minimal requirements:**
   ```bash
   rm -rf venv
   python3 -m venv venv
   source venv/bin/activate
   pip install --upgrade pip
   pip install -r requirements-minimal.txt
   ```

3. **Install build tools (macOS):**
   ```bash
   xcode-select --install
   brew install python3
   ```

4. **Install build tools (Ubuntu/Debian):**
   ```bash
   sudo apt update
   sudo apt install python3-dev python3-pip build-essential
   ```

5. **Use Python 3.11/3.12 (recommended for stability):**
   ```bash
   # With pyenv (recommended)
   pyenv install 3.12.0
   pyenv local 3.12.0
   rm -rf venv
   ./run.sh
   
   # Or use older system Python
   /usr/bin/python3.11 -m venv venv  # if available
   source venv/bin/activate
   pip install -r requirements.txt
   ```

6. **Python 3.13 specific fix:**
   ```bash
   rm -rf venv
   python3 -m venv venv
   source venv/bin/activate
   pip install --upgrade pip setuptools wheel
   pip install -r requirements.txt
   ```

**Configure environment:**
- Get your API key from: https://makersuite.google.com/app/apikey
- Add to `.env`:
  ```
  GEMINI_API_KEY=your_actual_gemini_api_key_here
  ```

The service will start on `http://127.0.0.1:8001`

## API Endpoints

### POST /chat
Processes chat messages with optional search context from Drupal.

**Request:**
```json
{
  "message": "What is Drupal?",
  "history": [
    {"role": "user", "content": "Hi", "timestamp": "2024-01-01T10:00:00Z"},
    {"role": "assistant", "content": "Hello! How can I help?", "timestamp": "2024-01-01T10:00:01Z"}
  ],
  "search_context": "Relevant content from Drupal search...",
  "user_id": "123",
  "system_prompt": "Custom system prompt"
}
```

**Response:**
```json
{
  "response": "Drupal is a content management system...",
  "status": "success"
}
```

### GET /health
Health check endpoint.

## Integration Flow

1. **User sends message** to Drupal chat interface
2. **Drupal performs Search API query** for relevant content
3. **Drupal calls Python API** with message, history, and search context
4. **Python calls Gemini** with formatted prompt including search context
5. **Python returns AI response** to Drupal
6. **Drupal updates chat interface** with response

## Configuration

- `GEMINI_API_KEY`: Your Google Gemini API key
- `GEMINI_MODEL`: Model to use (default: gemini-1.5-pro)
- `FASTAPI_HOST`: Host to bind to (default: 127.0.0.1)
- `FASTAPI_PORT`: Port to bind to (default: 8001)