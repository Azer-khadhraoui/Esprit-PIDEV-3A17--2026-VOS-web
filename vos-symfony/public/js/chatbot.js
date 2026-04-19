class ChatbotManager {
    constructor() {
        this.widget = document.getElementById('chatbot-widget');
        this.messagesContainer = document.getElementById('chatbot-messages');
        this.form = document.getElementById('chatbot-form');
        this.input = document.getElementById('chatbot-input');
        this.sendBtn = document.querySelector('.chatbot-send-btn');
        this.toggleBtn = document.getElementById('chatbot-toggle');
        this.suggestionsContainer = document.getElementById('chatbot-suggestions');

        this.conversationHistory = [];
        this.isMinimized = false;

        this.init();
    }

    init() {
        this.form.addEventListener('submit', (e) => this.handleSendMessage(e));
        this.toggleBtn.addEventListener('click', () => this.toggleWidget());
        this.input.addEventListener('input', (e) => this.handleInput(e));
        
        // Restore conversation from sessionStorage
        const saved = sessionStorage.getItem('chatbot_history');
        if (saved) {
            this.conversationHistory = JSON.parse(saved);
            this.renderConversation();
        }
    }

    toggleWidget() {
        this.isMinimized = !this.isMinimized;
        this.widget.classList.toggle('minimized');
        this.toggleBtn.textContent = this.isMinimized ? '☐' : '✕';
    }

    async handleSendMessage(e) {
        e.preventDefault();

        const message = this.input.value.trim();
        if (!message) return;

        // Ajouter le message utilisateur
        this.addMessage(message, 'user');
        this.input.value = '';
        this.suggestionsContainer.innerHTML = '';

        // Montrer l'indicateur de chargement
        this.addLoadingIndicator();

        try {
            const response = await fetch('/api/chatbot/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    message: message,
                    history: this.conversationHistory
                })
            });

            const data = await response.json();

            // Supprimer l'indicateur de chargement
            this.removeLoadingIndicator();

            if (data.success) {
                this.addMessage(data.response, 'bot');
                // Mettre à jour l'historique
                this.conversationHistory.push({ role: 'user', content: message });
                this.conversationHistory.push({ role: 'assistant', content: data.response });
                sessionStorage.setItem('chatbot_history', JSON.stringify(this.conversationHistory));
            } else {
                this.addMessage('Désolé, j\'ai rencontré une erreur: ' + (data.error || 'Erreur inconnue'), 'bot');
            }
        } catch (error) {
            this.removeLoadingIndicator();
            this.addMessage('Erreur de connexion. Veuillez réessayer.', 'bot');
            console.error('Chatbot error:', error);
        }
    }

    addMessage(text, sender) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `chatbot-message ${sender}-message`;

        const p = document.createElement('p');
        p.textContent = text;

        messageDiv.appendChild(p);
        this.messagesContainer.appendChild(messageDiv);

        // Scroll vers le bas
        this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
    }

    addLoadingIndicator() {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'chatbot-message bot-message';
        messageDiv.id = 'loading-indicator';

        const p = document.createElement('p');
        p.className = 'chatbot-loading';
        p.innerHTML = '<span></span><span></span><span></span>';

        messageDiv.appendChild(p);
        this.messagesContainer.appendChild(messageDiv);
        this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
    }

    removeLoadingIndicator() {
        const indicator = document.getElementById('loading-indicator');
        if (indicator) {
            indicator.remove();
        }
    }

    async handleInput(e) {
        const value = e.target.value.trim();
        
        if (value.length < 2) {
            this.suggestionsContainer.innerHTML = '';
            return;
        }

        try {
            const response = await fetch(`/api/chatbot/suggestions?q=${encodeURIComponent(value)}`);
            const data = await response.json();

            if (data.success && data.suggestions.length > 0) {
                this.renderSuggestions(data.suggestions);
            }
        } catch (error) {
            console.error('Error fetching suggestions:', error);
        }
    }

    renderSuggestions(suggestions) {
        this.suggestionsContainer.innerHTML = '';

        suggestions.forEach(suggestion => {
            const item = document.createElement('div');
            item.className = 'suggestion-item';
            item.textContent = suggestion.title;
            item.addEventListener('click', () => {
                this.input.value = suggestion.title;
                this.suggestionsContainer.innerHTML = '';
            });
            this.suggestionsContainer.appendChild(item);
        });
    }

    renderConversation() {
        this.messagesContainer.innerHTML = '';
        
        // Message initial
        const initialDiv = document.createElement('div');
        initialDiv.className = 'chatbot-message bot-message';
        const initialP = document.createElement('p');
        initialP.textContent = 'Bienvenue ! Je suis votre assistant. Comment puis-je vous aider avec vos offres d\'emploi ou vos candidatures ?';
        initialDiv.appendChild(initialP);
        this.messagesContainer.appendChild(initialDiv);

        // Rendre l'historique
        this.conversationHistory.forEach(msg => {
            const messageDiv = document.createElement('div');
            messageDiv.className = `chatbot-message ${msg.role === 'user' ? 'user' : 'bot'}-message`;
            const p = document.createElement('p');
            p.textContent = msg.content;
            messageDiv.appendChild(p);
            this.messagesContainer.appendChild(messageDiv);
        });

        this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
    }
}

// Initialiser le chatbot au chargement du DOM
document.addEventListener('DOMContentLoaded', () => {
    new ChatbotManager();
});
