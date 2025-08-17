from dotenv import load_dotenv
import logging
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
import os
import google.generativeai as genai
from pydantic import BaseModel
from typing import List, Dict, Any, Optional




# Load environment variables
load_dotenv()

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(title="AI Chat Service", version="1.0.0")

# Configure CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # Configure this for production
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Configure Gemini
gemini_api_key = os.getenv("GEMINI_API_KEY")
if not gemini_api_key:
    logger.error("GEMINI_API_KEY not found in environment variables")
else:
    genai.configure(api_key=gemini_api_key)

class ChatMessage(BaseModel):
    role: str
    content: str
    timestamp: Optional[str] = None

class ChatRequest(BaseModel):
    message: str
    history: List[ChatMessage] = []
    search_context: Optional[str] = None
    user_id: Optional[str] = None
    system_prompt: Optional[str] = None

class ChatResponse(BaseModel):
    response: str
    status: str = "success"
    error: Optional[str] = None

@app.get("/")
async def root():
    return {"message": "AI Chat Service is running"}

@app.get("/health")
async def health_check():
    return {"status": "healthy", "service": "AI Chat Service"}

@app.post("/chat", response_model=ChatResponse)
async def chat_endpoint(request: ChatRequest):
    try:
        logger.info(f"Received chat request from user {request.user_id}: {request.message}")
        
        # Validate Gemini API key
        if not gemini_api_key:
            raise HTTPException(status_code=500, detail="Gemini API key not configured")
        
        # Build conversation for Gemini
        system_prompt = request.system_prompt or """
            You are a helpful AI assistant that answers questions based on provided context. 
            If search context is provided, use it to give accurate and detailed answers. 
            If no context is provided or it's not relevant, provide helpful responses based on your knowledge.
            Be conversational and friendly."""
        
        # Build conversation history
        conversation_parts = []
        
        # Add system prompt as first message
        conversation_parts.append(f"System: {system_prompt}")
        
        # Add conversation history
        for msg in request.history[-10:]:  # Limit to last 10 messages
            role = "Human" if msg.role == "user" else "Assistant"
            conversation_parts.append(f"{role}: {msg.content}")
        
        # Prepare the current message with search context if available
        current_message = request.message
        if request.search_context:
            current_message = f"""Question: {request.message}
            # Search Context:
            # {request.search_context}

            # Please answer the question using the provided search context when relevant."""
                
        conversation_parts.append(f"Human: {current_message}")
        
        # Join all parts into a single prompt
        full_prompt = "\n\n".join(conversation_parts)
        
        # Log the prompt being sent to Gemini (for debugging)
        logger.info(f"Sending prompt to Gemini: {full_prompt[:200]}...")
        
        # Call Gemini API
        model_name = os.getenv("GEMINI_MODEL", "gemini-1.5-pro")
        model = genai.GenerativeModel(model_name)
        
        generation_config = genai.types.GenerationConfig(
            max_output_tokens=1000,
            temperature=0.7,
        )
        
        response = model.generate_content(
            full_prompt,
            generation_config=generation_config
        )
        
        ai_response = response.text
        logger.info(f"Received response from Gemini: {ai_response[:100]}...")
        
        return ChatResponse(
            response=ai_response,
            status="success"
        )
        
    except Exception as e:
        if "API" in str(e) or "quota" in str(e).lower():
            logger.error(f"Gemini API error: {e}")
            return ChatResponse(
                response="I'm sorry, I'm having trouble connecting to the AI service right now. Please try again later.",
                status="error",
                error=str(e)
            )
        else:
            logger.error(f"Unexpected error: {e}")
            raise HTTPException(status_code=500, detail=f"Internal server error: {str(e)}")

if __name__ == "__main__":
    import uvicorn
    host = os.getenv("FASTAPI_HOST", "0.0.0.0")
    port = int(os.getenv("FASTAPI_PORT", "8001"))
    uvicorn.run("main:app", host=host, port=port, reload=True, workers=2)