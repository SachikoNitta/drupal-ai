import React, { useState, useEffect, useRef } from 'react';

const ChatApp = ({ settings }) => {
  const [messages, setMessages] = useState([]);
  const [inputMessage, setInputMessage] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const messagesEndRef = useRef(null);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  useEffect(() => {
    scrollToBottom();
  }, [messages]);

  useEffect(() => {
    // Add welcome message
    setMessages([{
      id: 'welcome',
      role: 'assistant',
      content: `Hello ${settings.userName || 'there'}! I'm your AI assistant. How can I help you today?`,
      timestamp: new Date(),
    }]);
  }, [settings.userName]);

  const sendMessage = async () => {
    if (!inputMessage.trim() || isLoading) return;

    const userMessage = {
      id: Date.now().toString(),
      role: 'user',
      content: inputMessage,
      timestamp: new Date(),
    };

    setMessages(prev => [...prev, userMessage]);
    setInputMessage('');
    setIsLoading(true);

    try {
      const response = await fetch(settings.apiEndpoint || '/api/chat', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          message: inputMessage,
          history: messages,
        }),
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();

      const aiMessage = {
        id: (Date.now() + 1).toString(),
        role: 'assistant',
        content: data.response,
        timestamp: new Date(data.timestamp),
        searchResults: data.search_results || null,
        type: data.type || 'text',
      };

      setMessages(prev => [...prev, aiMessage]);
    } catch (error) {
      console.error('Chat error:', error);
      const errorMessage = {
        id: (Date.now() + 1).toString(),
        role: 'assistant',
        content: 'Sorry, I encountered an error. Please try again.',
        timestamp: new Date(),
      };
      setMessages(prev => [...prev, errorMessage]);
    } finally {
      setIsLoading(false);
    }
  };

  const handleKeyPress = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  };

  const formatTimestamp = (timestamp) => {
    return new Date(timestamp).toLocaleTimeString([], {
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  const renderSearchResults = (searchResults) => {
    if (!searchResults || searchResults.length === 0) {
      return null;
    }

    return (
      <div className="ai-chat-search-results">
        <h4>📋 Search Results:</h4>
        <ul className="ai-chat-results-list">
          {searchResults.map((result, index) => (
            <li key={index} className="ai-chat-result-item">
              <div className="ai-chat-result-header">
                <a 
                  href={result.url} 
                  target="_blank" 
                  rel="noopener noreferrer"
                  className="ai-chat-result-link"
                >
                  📄 {result.title}
                </a>
                {result.content_type && (
                  <span className="ai-chat-result-type">[{result.content_type}]</span>
                )}
                <span className="ai-chat-result-score">
                  {Math.round(result.score * 100)}% relevance
                </span>
              </div>
              {result.summary && (
                <p className="ai-chat-result-summary">{result.summary}</p>
              )}
              {result.author && (
                <small className="ai-chat-result-author">by {result.author}</small>
              )}
            </li>
          ))}
        </ul>
      </div>
    );
  };

  return (
    <div className="ai-chat-container">
      <div className="ai-chat-header">
        <h2>🤖 AI Chat Assistant</h2>
        <p>Welcome, {settings.userName || 'Guest'}!</p>
      </div>
      
      <div className="ai-chat-messages">
        {messages.map((message) => (
          <div
            key={message.id}
            className={`ai-chat-message ${message.role === 'user' ? 'user' : 'assistant'}`}
          >
            <div className="ai-chat-message-content">
              <div className="ai-chat-message-text">
                {message.content}
              </div>
              {message.searchResults && renderSearchResults(message.searchResults)}
              <div className="ai-chat-message-time">
                {formatTimestamp(message.timestamp)}
              </div>
            </div>
          </div>
        ))}
        
        {isLoading && (
          <div className="ai-chat-message assistant">
            <div className="ai-chat-message-content">
              <div className="ai-chat-typing">
                <span></span>
                <span></span>
                <span></span>
              </div>
            </div>
          </div>
        )}
        
        <div ref={messagesEndRef} />
      </div>
      
      <div className="ai-chat-input">
        <div className="ai-chat-input-container">
          <textarea
            value={inputMessage}
            onChange={(e) => setInputMessage(e.target.value)}
            onKeyPress={handleKeyPress}
            placeholder="Type your message here... (Press Enter to send)"
            disabled={isLoading}
            rows="2"
          />
          <button
            onClick={sendMessage}
            disabled={!inputMessage.trim() || isLoading}
            className="ai-chat-send-button"
          >
            {isLoading ? '⏳' : '📤'}
          </button>
        </div>
      </div>
    </div>
  );
};

export default ChatApp;