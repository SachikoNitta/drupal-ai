#!/bin/bash

# Change to script directory
cd "$(dirname "$0")"

echo "🚀 Setting up Python AI Chat Service (Python 3.13 compatible)..."

# Create virtual environment if it doesn't exist
if [ ! -d "venv" ]; then
    echo "Creating virtual environment..."
    python3 -m venv venv
    if [ $? -ne 0 ]; then
        echo "❌ Failed to create virtual environment. Make sure python3 is installed."
        exit 1
    fi
fi

# Activate virtual environment
echo "Activating virtual environment..."
source venv/bin/activate

# Upgrade pip
echo "Upgrading pip..."
pip install --upgrade pip

# Install requirements with Python 3.13 compatibility
echo "Installing requirements (Python 3.13 compatible)..."
pip install -r requirements.txt
if [ $? -ne 0 ]; then
    echo "❌ Failed to install requirements.txt, trying minimal requirements..."
    pip install -r requirements-minimal.txt
    if [ $? -ne 0 ]; then
        echo "❌ Failed to install minimal requirements. Trying individual packages..."
        pip install fastapi uvicorn google-generativeai python-dotenv pydantic httpx
        if [ $? -ne 0 ]; then
            echo "❌ Failed to install packages. Check your Python setup and internet connection."
            echo "💡 Try these troubleshooting steps:"
            echo "   1. Use Python 3.11 or 3.12: pyenv install 3.12.0 && pyenv local 3.12.0"
            echo "   2. Install build tools: xcode-select --install (macOS)"
            echo "   3. Update pip: python3 -m pip install --upgrade pip"
            exit 1
        fi
    fi
fi

# Create .env file if it doesn't exist
if [ ! -f ".env" ]; then
    echo "Creating .env file from example..."
    cp .env.example .env
    echo "Please edit .env file and add your Gemini API key"
    echo "Get your API key from: https://makersuite.google.com/app/apikey"
fi

# Check if .env has been configured
if grep -q "your_gemini_api_key_here" .env; then
    echo "⚠️  WARNING: Please edit .env file and add your Gemini API key"
    echo "   Edit: python/.env"
    echo "   Get key: https://makersuite.google.com/app/apikey"
    echo ""
fi

# Run the FastAPI server
echo "Starting FastAPI server..."
echo "Server will be available at: http://127.0.0.1:8001"
echo "API docs at: http://127.0.0.1:8001/docs"
echo "Press Ctrl+C to stop"
echo ""
python main.py