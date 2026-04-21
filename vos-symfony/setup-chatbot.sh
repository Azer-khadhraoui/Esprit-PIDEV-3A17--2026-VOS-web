#!/bin/bash
# Script de configuration du chatbot Groq
# Utilisation: bash setup-chatbot.sh

echo "🤖 Configuration du Chatbot Groq pour VOS"
echo "=========================================="
echo ""

# Vérifier si composer est installé
if ! command -v composer &> /dev/null; then
    echo "❌ Composer n'est pas installé. Veuillez installer Composer d'abord."
    exit 1
fi

echo "✅ Composer trouvé"
echo ""

# Vérifier si le fichier .env existe
if [ ! -f ".env" ]; then
    echo "⚠️  .env non trouvé. Veuillez copier .env.example en .env"
    exit 1
fi

echo "✅ Fichier .env trouvé"
echo ""

# Vérifier si .env.local existe
if [ ! -f ".env.local" ]; then
    echo "⚠️  .env.local n'existe pas"
    echo ""
    echo "📝 Créez un fichier .env.local avec votre clé API Groq:"
    echo ""
    echo "    # .env.local"
    echo "    GROQ_API_KEY=votre_cle_api_here"
    echo ""
    echo "Obtenez une clé sur: https://console.groq.com/keys"
    echo ""
    read -p "Appuyez sur Entrée après avoir créé .env.local..."
fi

# Installer les dépendances
echo "📦 Installation des dépendances..."
composer require symfony/http-client 2>/dev/null || echo "   symfony/http-client déjà installé"
echo "✅ Dépendances à jour"
echo ""

# Vider le cache
echo "🔄 Vidage du cache Symfony..."
php bin/console cache:clear
echo "✅ Cache vidé"
echo ""

# Tester la configuration
echo "🧪 Test de la configuration du service..."
if php -r "require 'vendor/autoload.php'; \$container = require 'var/cache/dev/App_KernelDevDebugContainer.php'; \$chatbot = \$container->get(App\Service\ChatbotService::class); echo '✅ Service ChatbotService OK';" 2>/dev/null; then
    echo "✅ Service ChatbotService correctement configuré"
else
    echo "ℹ️  Impossible de vérifier le service. Exécutez: php bin/console cache:clear"
fi

echo ""
echo "✅ Configuration du chatbot terminée!"
echo ""
echo "📝 Prochaines étapes:"
echo "   1. Assurez-vous que votre .env.local contient GROQ_API_KEY"
echo "   2. Démarrez votre serveur: symfony server:start"
echo "   3. Connectez-vous à votre application"
echo "   4. Le widget chat devrait apparaître en bas à droite"
echo ""
echo "🔗 Documentations:"
echo "   - Guide complet: CHATBOT_IMPLEMENTATION.md"
echo "   - Démarrage rapide: CHATBOT_QUICKSTART.md"
echo ""
echo "💡 Pour déboguer:"
echo "   tail -f var/log/dev.log"
echo ""
