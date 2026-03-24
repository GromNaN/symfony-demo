# RangeTagGeneratorFactory - Refactorisation

La création et la configuration de `RangeTagGenerator` a été factorisée dans un service dédié : `RangeTagGeneratorFactory`.

## Avant (duplication)

```php
// Dans QueryableEncryptionSubscriber
$birthGenerator = new RangeTagGenerator(
    sparsity: 4,
    precision: 0,
    min: -2208988800000.0,
    max: 4102444800000.0,
    fieldId: 1,
    dekStore: $this->dekStore,
    dekId: 'birthdate-tag-key'
);

// Dans UserQeRepository (duplication)
$generator = new RangeTagGenerator(
    sparsity: 4,
    precision: 0,
    min: 0.0,
    max: 1000000.0,
    fieldId: 2,
    dekStore: $this->dekStore,
    dekId: 'income-tag-key'
);
```

## Après (factory)

```php
// Service injecté
public function __construct(
    private readonly RangeTagGeneratorFactory $generatorFactory,
) {}

// Utilisation simple et claire
$birthdateGenerator = $this->generatorFactory->forBirthdate();
$incomeGenerator = $this->generatorFactory->forYearlyIncome();
```

## API de RangeTagGeneratorFactory

### Méthodes pré-configurées

```php
// Pour les champs standard
$generator = $factory->forBirthdate();      // field_id=1, range 1900-2100
$generator = $factory->forYearlyIncome();   // field_id=2, range 0-1M
```

### Méthode pour champs personnalisés

```php
$generator = $factory->create(
    fieldId: 99,
    min: 0.0,
    max: 100.0,
    dekId: 'custom-key',
    sparsity: 3,        // optionnel
    precision: 2        // optionnel
);
```

## Avantages

1. **Pas de duplication** : Configuration centralisée
2. **Maintenabilité** : Changer la sparsity/precision d'un champ en un seul endroit
3. **Testabilité** : Facile de tester la factory séparément
4. **Extensibilité** : Ajouter un nouveau champ QE = ajouter une méthode à la factory
5. **Typage** : Injectée en tant que dépendance, avec autocomplétion IDE

## Injection dans les services

```php
// QueryableEncryptionSubscriber
final class QueryableEncryptionSubscriber
{
    public function __construct(
        private readonly RangeTagGeneratorFactory $generatorFactory,
    ) {}
}

// UserQeRepository
final class UserQeRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly RangeTagGeneratorFactory $generatorFactory,
    ) {
        parent::__construct($registry, UserQe::class);
    }
}
```

## Enregistrement automatique dans Symfony

La factory est **enregistrée automatiquement** via l'autodiscovery de Symfony (`App\` namespace).

## Tests

- `RangeTagGeneratorFactoryTest` : vérifie la création des générateurs
- `UserQeRepositoryTest` : vérifie le comportement des queries (changements transparents)
- `QueryableEncryptionSubscriber` : utilise la factory automatiquement

## Exemple d'ajout d'un nouveau champ

Pour ajouter un champ QE pour "date d'embauche" :

1. Ajouter la méthode à la factory :
   ```php
   public function forHiringDate(): RangeTagGenerator
   {
       return new RangeTagGenerator(
           sparsity: 4,
           precision: 0,
           min: -3155760000000.0,   // 1970-01-01
           max: 4102444800000.0,    // 2100-01-01
           fieldId: 3,
           dekStore: $this->dekStore,
           dekId: 'hiring-date-tag-key'
       );
   }
   ```

2. Utiliser dans le subscriber :
   ```php
   if ($user->hiringDate !== null) {
       $generator = $this->generatorFactory->forHiringDate();
       $tags = $generator->generateValueTags(...);
       // ...
   }
   ```

3. Ajouter une méthode de recherche au repository :
   ```php
   public function findByHiringDateRange(\DateTimeInterface $min, \DateTimeInterface $max): array
   {
       $generator = $this->generatorFactory->forHiringDate();
       // ...
   }
   ```

## Configuration centralisée

Pour changer les paramètres d'un champ (ex: augmenter la sparsity pour plus de précision de recherche), modifier une seule méthode de la factory :

```php
public function forYearlyIncome(): RangeTagGenerator
{
    return new RangeTagGenerator(
        sparsity: 5,  // Augmenté de 4 à 5
        precision: 0,
        min: 0.0,
        max: 1000000.0,
        fieldId: 2,
        dekStore: $this->dekStore,
        dekId: 'income-tag-key'
    );
}
```

Tous les services utilisant la factory auront automatiquement la nouvelle configuration.

