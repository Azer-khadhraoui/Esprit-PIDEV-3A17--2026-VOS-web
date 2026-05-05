# PHPStan Wrapper Function - clean output with increased memory
function phpstan-clean {
    param([string]$args)
    & php -d memory_limit=512M vendor/bin/phpstan $args 2>&1 | Where-Object { $_ -notmatch "Note: Using configuration" }
}

# Usage examples:
# phpstan-clean "analyse src/Entity/OffreEmploi.php"
# phpstan-clean "analyse src/Entity/OffreEmploi.php src/Entity/CritereOffre.php"
# phpstan-clean "analyse src/Service/OffreEmploiManager.php"
