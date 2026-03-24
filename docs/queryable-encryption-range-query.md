# UserQeRepository - Range Query Example

Cette classe démontre comment utiliser `RangeTagGenerator` pour faire des recherches par plage de valeurs sur des champs chiffrés avec Queryable Encryption.

## Utilisation

### Recherche par plage d'income

```php
// Dans un contrôleur ou service
public function __construct(
    private readonly UserQeRepository $userQeRepository,
) {}

public function findByIncomeRange(): void
{
    // Trouver tous les utilisateurs avec un revenu entre 45 000 et 55 000 €
    $users = $this->userQeRepository->findByIncomeRange(45000, 55000);
    
    foreach ($users as $user) {
        echo "User income: " . $user->yearlyIncome . "\n";
    }
}
```

### Recherche par revenu exact

```php
// Trouver un utilisateur avec un revenu exact de 50 000 €
$user = $this->userQeRepository->findByExactIncome(50000);

if ($user !== null) {
    echo "Found user with exact income: " . $user->yearlyIncome;
}
```

## Comment ça marche ?

### 1. Génération des tags

Le repository crée un `RangeTagGenerator` avec les paramètres :
- **min/max** : La plage globale de valeurs (0 à 1 000 000 pour les revenus)
- **fieldId** : 2 pour les revenus (FIELD_YEARLY_INCOME)
- **sparsity** : 4 niveaux (défaut)
- **precision** : 0 (pas de décimales)
- **dekStore** : Pour récupérer la clé de dérivation de tag

### 2. Génération des tags de recherche

```php
$tags = $generator->generateRangeQueryTags(45000, 55000);
```

Cela génère tous les tags HMAC qui correspondent aux valeurs dans la plage.

### 3. Requête base de données

La requête utilise `LIKE` pour chercher les utilisateurs dont `safeContent` contient au moins un de ces tags :

```sql
SELECT u FROM UserQe u
WHERE u.safeContent LIKE '%tag1%'
   OR u.safeContent LIKE '%tag2%'
   OR u.safeContent LIKE '%tag3%'
   ...
```

## Exemple complet

```php
// Service métier
final class UserIncomeService
{
    public function __construct(
        private readonly UserQeRepository $repo,
    ) {}

    public function findMidIncomeUsers(): array
    {
        return $this->repo->findByIncomeRange(40000, 60000);
    }

    public function findHighEarner(int $targetIncome): ?UserQe
    {
        return $this->repo->findByExactIncome($targetIncome);
    }
}

// Utilisation
$service = new UserIncomeService($repository);

$midIncomeUsers = $service->findMidIncomeUsers();
echo "Found " . count($midIncomeUsers) . " users with income 40-60k\n";

$highEarner = $service->findHighEarner(100000);
if ($highEarner !== null) {
    echo "Found user earning 100k\n";
}
```

## Notes de sécurité

- Les tags sont dérivés de manière **déterministe** à partir des limites de plage
- Chaque tag incorpore:
  - Le `fieldId` (identifie le champ)
  - Le `level` (niveau de l'arbre multi-niveaux)
  - Le `bucketIndex` (position dans la segmentation)
- Les tags sont hashés avec HMAC-SHA256
- La clé HMAC est dérivée de la DEK (Data Encryption Key)

## Limitations

- `MAX_RANGE_TAGS = 1000` : Si une plage génère plus de 1000 tags, une exception est lancée
- Faux positifs possibles : Due à la nature des tags de recherche (par conception de QE)
- Performance : Chaque tag requiert une comparaison LIKE supplémentaire

