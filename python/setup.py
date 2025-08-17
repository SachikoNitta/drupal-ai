#!/usr/bin/env python3
"""
Alternative setup script for Python environment.
Use this if run.sh fails or you prefer Python-based setup.
"""

import subprocess
import sys
import os
from pathlib import Path

def run_command(cmd, description):
    """Run a command and handle errors."""
    print(f"🔄 {description}...")
    try:
        result = subprocess.run(cmd, shell=True, check=True, capture_output=True, text=True)
        print(f"✅ {description} completed")
        return True
    except subprocess.CalledProcessError as e:
        print(f"❌ {description} failed: {e.stderr}")
        return False

def main():
    # Change to script directory
    script_dir = Path(__file__).parent
    os.chdir(script_dir)
    
    print("🚀 Setting up Python AI Chat Service...")
    print(f"📁 Working directory: {script_dir}")
    
    # Check Python version
    if sys.version_info < (3, 8):
        print("❌ Python 3.8+ is required")
        sys.exit(1)
    
    print(f"✅ Python {sys.version_info.major}.{sys.version_info.minor} detected")
    
    # Create virtual environment
    if not Path("venv").exists():
        if not run_command(f"{sys.executable} -m venv venv", "Creating virtual environment"):
            sys.exit(1)
    else:
        print("✅ Virtual environment already exists")
    
    # Determine activation command
    if os.name == 'nt':  # Windows
        activate_cmd = "venv\\Scripts\\activate"
        python_cmd = "venv\\Scripts\\python"
        pip_cmd = "venv\\Scripts\\pip"
    else:  # Unix/MacOS
        activate_cmd = "source venv/bin/activate"
        python_cmd = "venv/bin/python"
        pip_cmd = "venv/bin/pip"
    
    # Upgrade pip
    run_command(f"{pip_cmd} install --upgrade pip", "Upgrading pip")
    
    # Install requirements
    if not run_command(f"{pip_cmd} install -r requirements.txt", "Installing requirements"):
        print("❌ Failed to install requirements. Check your internet connection.")
        sys.exit(1)
    
    # Create .env file
    if not Path(".env").exists():
        if Path(".env.example").exists():
            run_command("cp .env.example .env", "Creating .env file")
            print("⚠️  Please edit .env file and add your Gemini API key")
            print("   Get key: https://makersuite.google.com/app/apikey")
        else:
            print("❌ .env.example not found")
    else:
        print("✅ .env file already exists")
    
    print("\n🎉 Setup complete!")
    print("\nTo start the server:")
    print(f"  {python_cmd} main.py")
    print("\nOr use the run script:")
    print("  ./run.sh")

if __name__ == "__main__":
    main()