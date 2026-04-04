# -Esprit-PIDEV-3A17--2026-VOS-web

## Architecture MVC (Symfony)

Le module user est organise selon le pattern MVC.

### Model
- Entities Doctrine: `vos-symfony/src/Entity/`
- Repositories: `vos-symfony/src/Repository/`
- Logique metier: `vos-symfony/src/Service/`
- Donnees formulaire (DTO): `vos-symfony/src/Dto/`

### View
- Templates Twig: `vos-symfony/templates/`
- Styles CSS: `vos-symfony/public/styles/`

### Controller
- Controleurs HTTP: `vos-symfony/src/Controller/`

### Flux actuel
1. Le Controller recoit la requete.
2. Il delegue la logique metier au Service (Model applicatif).
3. Le Service utilise Repository/Entity pour lire ou ecrire en base.
4. Le Controller retourne une View Twig.
