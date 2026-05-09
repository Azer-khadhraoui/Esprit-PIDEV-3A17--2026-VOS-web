# VOS - Virtual Onboarding System

<p align="center">
  <img src="public/images/logo.png" alt="VOS logo" width="190" style="vertical-align: middle; margin-right: 18px;">
  <img src="public/images/VOSslogan_1770834966459.png" alt="VOS slogan" width="420" style="vertical-align: middle;">
</p>

<p align="center">
  <img alt="Symfony" src="https://img.shields.io/badge/Symfony-6.4-111111?style=for-the-badge&logo=symfony">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8.2-777BB4?style=for-the-badge&logo=php&logoColor=white">
  <img alt="Doctrine" src="https://img.shields.io/badge/Doctrine-ORM%20%2B%20Migrations-DB4D37?style=for-the-badge">
  <img alt="Twig" src="https://img.shields.io/badge/Twig-Templates-84cc16?style=for-the-badge">
</p>

<p align="center">
  <img alt="RH" src="https://img.shields.io/badge/RH-Recrutement-0F172A?style=flat-square">
  <img alt="IA" src="https://img.shields.io/badge/IA-Groq%20%2B%20LanguageTool-1D4ED8?style=flat-square">
  <img alt="Calendar" src="https://img.shields.io/badge/Sync-Google%20Calendar-EA4335?style=flat-square">
  <img alt="Auth" src="https://img.shields.io/badge/Auth-Face%20ID-059669?style=flat-square">
</p>

VOS est une application Symfony de gestion RH et recrutement. Elle centralise les candidatures, les entretiens, les recrutements, les contrats, la generation de PDF, les notifications par email et plusieurs fonctions d'assistance par IA.

## Apercu rapide

| Module | Description |
| --- | --- |
| Candidatures | Depot, suivi, modification, export PDF et analyse |
| Entretiens | Planification, QR code public, evaluation et suivi |
| Recrutement | Decision finale, notifications et synchronisation agenda |
| Contrats | Gestion RH, rappels et suivi de fin de contrat |
| IA | Aide a la redaction, correction linguistique et suggestions |

## Fonctionnalites principales

- Authentification avec connexion classique et reconnaissance faciale.
- Gestion des candidatures client avec ajout, modification, suppression et export PDF.
- Gestion des offres, des criteres et des statistiques recrutement.
- Gestion des entretiens avec export PDF et QR code vers une page mobile publique.
- Gestion des contrats et rappels automatiques avant echeance.
- Envoi d'emails automatiques pour les candidatures, les contrats et les decisions.
- Amelioration et generation de contenu via Groq.
- Correction linguistique via LanguageTool.
- QR codes pour verifier ou consulter des documents rapidement.

## Stack technique

- Symfony 6.4
- PHP 8.1+
- Doctrine ORM / Migrations
- Twig
- Symfony Mailer
- Dompdf
- Endroid QR Code Bundle
- face-api.js cote navigateur
- Groq API pour les fonctions IA
- LanguageTool API pour la correction grammaticale
- Google Calendar pour certaines synchronisations

## Structure du projet

- `src/Controller` : controleurs HTTP.
- `src/Service` : services metiers, email, IA, PDF, calendrier.
- `src/Entity` : entites Doctrine.
- `src/Form` : formulaires Symfony.
- `templates` : vues Twig et templates email/PDF.
- `public` : fichiers accessibles publiquement, images, styles, uploads.
- `migrations` : migrations Doctrine.

## Installation

### 1. Installer les dependances

```bash
composer install
```

### 2. Configurer l'environnement

Copiez ou adaptez vos variables d'environnement dans `.env.local` ou `.env.dev.local`.

Variables utiles :

- `DATABASE_URL`
- `MAILER_DSN`
- `GROQ_API_KEY`
- `GROQ_MODEL`
- `APP_PUBLIC_URL`

### 3. Creer la base de donnees et executer les migrations

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

### 4. Lancer le serveur local

```bash
php -S 127.0.0.1:8000 -t public
```

Si vous avez le Symfony CLI installe, vous pouvez aussi utiliser :

```bash
symfony serve --no-tls
```

Pour un acces reseau local depuis un autre appareil, vous pouvez utiliser :

```bash
symfony serve --listen-ip=0.0.0.0 --no-tls
```

## Variables externes et APIs

Ce projet utilise plusieurs services externes :

- Groq pour la generation de texte IA.
- LanguageTool pour la correction linguistique.
- face-api.js charge depuis un CDN pour la reconnaissance faciale cote navigateur.
- Un transport mail configure par `MAILER_DSN`.

## Flux IA et email dans le recrutement

Le rappel de contrat suit ce principe :

1. Un command Symfony detecte les contrats proches de la date de fin.
2. `ContractReminderAiService` demande a Groq de generer un message court et professionnel.
3. `RecrutementNotificationService` envoie ensuite l'email avec le message genere.
4. Si Groq echoue, une version de secours est utilisee pour ne jamais bloquer l'envoi.

## Reconnaissance faciale

La connexion Face ID fonctionne cote navigateur avec JavaScript :

1. La camera est ouverte avec `getUserMedia`.
2. face-api.js extrait un descriptor facial.
3. Le descriptor live est compare avec la reference utilisateur.
4. Si la similarite est suffisante, le formulaire de connexion est valide.

## QR code dans le PDF d'entretien

Le PDF d'entretien contient un QR code qui pointe vers une page publique mobile de consultation rapide.

## Notes de maintenance base de donnees

Certaines bases locales de ce projet peuvent perdre la configuration `AUTO_INCREMENT` sur des cles primaires. Quand cela arrive, Doctrine leve en general l'erreur suivante :

```text
No identity value was generated by the last statement.
```

Des migrations de correction existent deja pour :

- `offre_emploi.id_offre`
- `utilisateur.id_utilisateur`
- `recrutement.id_recrutement`

Si la base locale a ete modifiee manuellement ou restauree hors migrations, verifiez ces colonnes en priorite.

## Correctifs recents

- Le champ `Candidature` du formulaire d'ajout d'entretien affiche maintenant le nom et le prenom du candidat.
- Les insertions sur `utilisateur` et `recrutement` ont ete stabilisees en restaurant `AUTO_INCREMENT` sur leurs cles primaires.

## Logo du projet

Le logo VOS est stocke dans :

- `public/images/logo.png`

Il est reutilise dans les documents PDF, les emails et ce README.

## Contributeurs

Les identites ci-dessous viennent de `git shortlog -sne --all`, verifiees sur le depot local. Certaines personnes ont plusieurs alias Git, donc les lignes sont conservees telles qu'elles apparaissent dans l'historique.

| Contributeur Git | Commits | Branche principale observee |
| --- | ---: | --- |
| Azer-khadhraoui <azerronaldo2004@gmail.com> | 58 | `Gestion-Utilisateur` |
| MAMIYASSINE <mamiy463@gmail.com> | 32 | `Gestion-Candidat` |
| yessine merhbene <mohamedyessin.merhbene@esprit.tn> | 27 | `Gestion-Entretien`, `GESTION-ENTRETIEN-INTEG` |
| omar belhaj <133699793+wrldomar@users.noreply.github.com> | 16 | `Gestion-Offre` |
| TanSuperNova <faresmanai05@gmail.com> | 10 | `Gestion-Recrutement` |
| Mohamed azer khadhraoui <azerronaldo2004@gmail.com> | 9 | alias Git observe |
| Yassine Mami <126829473+MAMIYASSINE@users.noreply.github.com> | 3 | alias Git observe |
| Yessine Merhbene <168140906+yessinemer@users.noreply.github.com> | 2 | alias Git observe |
| Fares Manai <faresmanai05@gmail.com> | 1 | alias Git observe |

Equipe VOS.

<p align="center">
  <img src="public/images/logo.png" alt="VOS logo" width="72">
</p>
