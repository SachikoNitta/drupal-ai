import React from 'react';
import { createRoot } from 'react-dom/client';
import ChatApp from './ChatApp';

// Wait for DOM and Drupal to be ready
document.addEventListener('DOMContentLoaded', function() {
  const container = document.getElementById('ai-chat-app');
  if (container) {
    const root = createRoot(container);
    
    // Get Drupal settings
    const settings = window.drupalSettings?.aiChat || {};
    
    root.render(<ChatApp settings={settings} />);
  }
});