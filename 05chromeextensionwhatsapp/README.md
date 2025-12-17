# WhatsHybrid Lite Chrome Extension

## 🔧 Recent Security & Feature Updates

### ✅ Security Improvements (CRITICAL)
- **Removed hardcoded API keys**: All API keys and secrets must now be configured by users via the popup
- **Restricted host permissions**: Limited to only necessary domains (web.whatsapp.com, api.openai.com, adm.redealabama.com)
- **Added Content Security Policy**: Prevents XSS and unauthorized script execution
- **Improved error logging**: All errors are now properly logged instead of being silently caught

### 🎨 UI/UX Enhancements
- **Premium Design**: Modern, polished interface with smooth animations
- **API Key Configuration**: Dedicated section for OpenAI API key with visibility toggle and connection test
- **Real-time Status**: Visual indicators for API connection status
- **Improved Visual Hierarchy**: Better spacing, typography, and color usage

### ⚡ Functional Improvements

#### Quick Replies
- Type `/trigger` in WhatsApp composer and press Enter
- Automatically inserts the configured response
- Fully functional in live chats

#### Campaign Routing Fix
- **Critical Fix**: Validates chat title matches target number before sending
- Prevents messages from going to wrong recipient
- Adds window focus to ensure correct tab processing

#### Phone Validation
- Supports international formats (10-15 digits)
- Multiple display formats for different country codes
- Duplicate number detection

#### Auto-Memory (Leão)
- Fully integrated and working
- Stores context per conversation
- Updates automatically or manually

#### AI Chatbot
- Uses real OpenAI API with user-configured key
- Generates actual responses (no mocks)
- Multiple modes: Reply, Summary, Follow-up, Training

#### Team Messaging
- Sends to configured team members
- Uses saved sender name
- Works with campaigns system

### 🐛 Bug Fixes
- `DEBUG_MODE` set to false for production
- Improved error handling throughout
- Better DOM selectors with fallbacks
- Race condition improvements

## 📋 Setup Instructions

### 1. Install the Extension
1. Open Chrome and go to `chrome://extensions/`
2. Enable "Developer mode"
3. Click "Load unpacked"
4. Select the `05chromeextensionwhatsapp` folder

### 2. Configure API Key
1. Click the extension icon to open the popup
2. In the "Configuração da API OpenAI" section:
   - Enter your OpenAI API key (get one from https://platform.openai.com/api-keys)
   - Click "Testar Conexão" to verify it works
   - Click "💾 Salvar Configurações"

### 3. Configure Chatbot
1. Fill in "Persona / Nome do Assistente"
2. Add your "Contexto do Negócio"
3. Enable "Auto-sugerir respostas" if desired
4. Enable "Auto-memória (Leão)" for conversation memory

### 4. Quick Replies
1. Go to "⚡ Rápidas" tab
2. Add triggers (e.g., "preco", "horario", "pix")
3. Add responses for each trigger
4. In WhatsApp, type `/preco` + Enter to use

### 5. Team Messaging
1. Go to "👥 Equipe" tab
2. Set your sender name
3. Add team members with names and phone numbers
4. Select members and send broadcast messages

## 🔒 Security Best Practices

### For Users
- Never share your API key
- Regularly rotate your OpenAI API key
- Review permissions before installing
- Use strong passwords for accounts

### For Developers
- Never commit API keys or secrets to version control
- Always use `chrome.storage.local` for sensitive data
- Keep dependencies updated
- Follow principle of least privilege for permissions

## 🛠️ Development

### File Structure
```
05chromeextensionwhatsapp/
├── manifest.json           # Extension configuration
├── background/
│   └── serviceWorker.js   # Background service worker (API calls)
├── content/
│   ├── content.js         # Main content script (WhatsApp integration)
│   ├── content.css        # Content script styles
│   └── injected.js        # Injected script for WhatsApp internals
└── popup/
    ├── popup.html         # Extension popup UI
    ├── popup.css          # Popup styles
    └── popup.js           # Popup logic
```

### Key Functions

#### `openChatBySearch(query)`
Opens a WhatsApp chat by phone number. Now includes validation to ensure correct recipient.

#### `insertIntoComposer(text, humanized, stealthMode)`
Inserts text into WhatsApp composer with multiple fallback methods.

#### `clickSend(stealthMode)`
Clicks the send button with optional stealth mode for rate limiting.

#### `aiChat({mode, extraInstruction, transcript, memory, chatTitle})`
Calls OpenAI API to generate responses based on conversation context.

### Testing
1. Test API key configuration and validation
2. Test quick replies in live chats
3. Test campaign routing with multiple recipients
4. Test team messaging
5. Verify no hardcoded secrets remain

## 📝 Changelog

### v0.2.2+ (Current)
- **Security**: Removed all hardcoded API keys and secrets
- **Security**: Added CSP and restricted permissions
- **Feature**: Quick replies with /trigger syntax
- **Fix**: Campaign routing validation
- **Fix**: Phone number validation improvements
- **UI**: Premium design refresh
- **Bug**: DEBUG_MODE disabled for production

## 🤝 Contributing
When contributing, ensure:
1. No secrets in code
2. Proper error handling
3. Tests for new features
4. Update this README

## 📄 License
See main repository license.

## 🆘 Support
For issues or questions, open an issue in the main repository.
