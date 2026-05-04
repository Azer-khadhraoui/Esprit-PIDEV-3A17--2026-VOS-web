# 📋 RAPPORT ANALYSE CODE - Gestion-Utilisateur

## 🔍 PHPStan - Analyse Statique (Level 5)

**Status**: ❌ 52 erreurs trouvées

### Erreurs principales:

#### 1. **PasswordResetService.php (3 erreurs)**
- Accès à méthode protégée `getEntityManager()` de Doctrine
- Expression booléenne toujours fausse (ligne 74)
- Suggestion: Ajouter `@phpstan-impure` aux getters

#### 2. **RecrutementNotificationService.php (1 erreur)**
- Méthode `isFinalDecision()` inutilisée → À supprimer ou documenter

### Types d'erreurs trouvées:
- `method.protected` - Appels de méthodes protégées
- `booleanNot.alwaysFalse` - Logique booléenne défectueuse
- `method.unused` - Méthodes non utilisées

### Action requise:
```bash
cd vos-symfony
./vendor/bin/phpstan analyse --level=5 src
```

---

## 🏥 Doctrine Doctor - Validation ORM

### ✅ Mapping - OK
```
10 entities mappées et valides:
  ✓ App\Entity\AnalyseCv
  ✓ App\Entity\Candidature
  ✓ App\Entity\ContratEmbauche
  ✓ App\Entity\CritereOffre
  ✓ App\Entity\Entretien
  ✓ App\Entity\EvaluationEntretien
  ✓ App\Entity\OffreEmploi
  ✓ App\Entity\PreferenceCandidature
  ✓ App\Entity\Recrutement
  ✓ App\Entity\User
```

### ❌ Database Schema - DÉSYNCHRONISÉ
```
Le schéma de la base de données n'est pas en sync avec les mappings
```

### Action requise:
```bash
php bin/console doctrine:migrations:sync-metadata-storage
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:schema:validate
```

---

## 📊 Résumé

| Outil | Statut | Problèmes |
|-------|--------|----------|
| **PHPStan** | ⚠️ | 52 erreurs (type, logique, code mort) |
| **Doctrine** | ⚠️ | Schema désynchronisé, mais entities OK |
| **Tests** | ✅ | 8/8 passent (24 assertions) |

---

## 🔧 Prochaines étapes

1. **Corriger PHPStan** - Fixer 52 erreurs détectées
2. **Synchroniser Doctrine** - Aligner le schema avec les mappings
3. **Re-valider** - Lancer les outils à nouveau
4. **Commit** - Pusher les corrections

---

## 📌 Commandes utiles

```bash
# PHPStan
./vendor/bin/phpstan analyse --level=5 src
./vendor/bin/phpstan analyse --level=5 src --fix  # Auto-fix si possible

# Doctrine
php bin/console doctrine:schema:validate
php bin/console doctrine:mapping:info
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate

# Tests
./vendor/bin/simple-phpunit tests -v
```

Generated: $(date)

---

**Afficher la barre d'erreurs dans l'application**

Le projet inclut une barre en bas de page qui s'affiche si un fichier `var/debug_bar.json` existe. Le contrôleur expose les diagnostics à `/_debug/bar`.

Pour générer rapidement `var/debug_bar.json` (exemples):

- Linux / WSL / macOS (exécuter depuis `vos-symfony`):

```bash
# Exemple simple : ajouter un message PHPStan + Doctrine
mkdir -p var
cat > var/debug_bar.json <<'JSON'
[
  {"title":"PHPStan","message":"Voir ./vendor/bin/phpstan analyse --level=5 src"},
  {"title":"Doctrine","message":"Schema not in sync — run doctrine:migrations:diff and review"}
]
JSON
```

- Windows PowerShell :

```powershell
mkdir var -ErrorAction SilentlyContinue
@('[{"title":"PHPStan","message":"Voir .\\vendor\\bin\\phpstan analyse --level=5 src"}, {"title":"Doctrine","message":"Schema not in sync — run doctrine:migrations:diff and review"}]') | Out-File -Encoding utf8 var\debug_bar.json
```

Plus avancé : produire dynamiquement des messages depuis les sorties d'outils, puis convertir en JSON (écrire un petit script PHP ou Node.js pour normaliser les sorties). Le contrôleur lit simplement `var/debug_bar.json` et la barre l'affiche.

Après création, ouvrez l'application ; la barre apparaîtra en bas avec les messages listés.
