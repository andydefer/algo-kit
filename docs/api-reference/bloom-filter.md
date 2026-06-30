# BloomFilter - Référence Technique

## Description

BloomFilter est une structure de données probabiliste qui teste l'appartenance d'un élément à un ensemble. Elle permet de répondre rapidement à la question "Cet élément a-t-il déjà été vu ?" avec un risque contrôlé de faux positifs.

## Hiérarchie / Implémentations

```
BloomFilterInterface
    └── BloomFilter
```

**Interfaces implémentées :** `BloomFilterInterface`

## Rôle principal

BloomFilter utilise un tableau de bits et plusieurs fonctions de hachage pour stocker les empreintes des éléments. Il permet de vérifier l'existence d'un élément avec une complexité O(k) où k est le nombre de fonctions de hachage, tout en utilisant très peu de mémoire. Particulièrement adapté pour les cas où la mémoire est limitée et où les faux positifs sont acceptables.

## Installation

```bash
composer require andydefer/algokit
```

## API / Méthodes publiques

### `__construct(StorageInterface $storage, int $size = 10000, int $hashCount = 3, string $key = 'bloom')`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$storage` | `StorageInterface` | Instance du système de stockage |
| `$size` | `int` | Taille du tableau de bits (défaut: 10000) |
| `$hashCount` | `int` | Nombre de fonctions de hachage (défaut: 3) |
| `$key` | `string` | Clé d'identification dans le storage (défaut: 'bloom') |

**Retourne :** `void`

**Exemple :**
```php
$storage = new MemoryStorage();
$bloom = new BloomFilter($storage, 10000, 3, 'urls');
```

---

### `insert(string $value): void`

Insère une valeur dans le filtre.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `string` | Valeur à insérer |

**Retourne :** `void`

**Exemple :**
```php
$bloom->insert('https://example.com');
$bloom->insert('user_123');
```

---

### `exists(string $value): bool`

Vérifie si une valeur existe probablement dans le filtre.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `string` | Valeur à vérifier |

**Retourne :** `bool` - `true` si la valeur existe probablement, `false` si elle n'existe pas

**Exemple :**
```php
if ($bloom->exists('https://example.com')) {
    echo "URL déjà indexée (probablement)";
}
```

---

### `insertBatch(BloomFilterCollection $collection): void`

Insère plusieurs valeurs en lot.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$collection` | `BloomFilterCollection` | Collection de valeurs à insérer |

**Retourne :** `void`

**Exemple :**
```php
$collection = new BloomFilterCollection();
$collection->add(new BloomFilterRecord('url1'));
$collection->add(new BloomFilterRecord('url2'));
$bloom->insertBatch($collection);
```

---

### `existsBatch(BloomFilterCollection $collection): BloomFilterResultCollection`

Vérifie plusieurs valeurs en lot.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$collection` | `BloomFilterCollection` | Collection de valeurs à vérifier |

**Retourne :** `BloomFilterResultCollection` - Collection des résultats avec le statut d'existence

**Exemple :**
```php
$results = $bloom->existsBatch($collection);
foreach ($results as $result) {
    echo "{$result->value}: " . ($result->exists ? 'exists' : 'not found');
}
```

---

### `clear(): void`

Vide complètement le filtre.

**Retourne :** `void`

**Exemple :**
```php
$bloom->clear();
```

## Cas d'utilisation

### Cas 1 : Détection d'URLs déjà indexées

```php
use AndyDefer\AlgoKIT\Algorithms\BloomFilter;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;

$storage = new MemoryStorage();
$bloom = new BloomFilter($storage, 100000, 3, 'url_index');

// Indexation des URLs
$urls = [
    'https://example.com/page1',
    'https://example.com/page2',
    'https://example.com/page3'
];

foreach ($urls as $url) {
    if (!$bloom->exists($url)) {
        $bloom->insert($url);
        echo "Nouvelle URL indexée: $url\n";
    } else {
        echo "URL déjà indexée: $url\n";
    }
}
```

### Cas 2 : Vérification de mots dans un dictionnaire

```php
// Création du dictionnaire
$storage = new MemoryStorage();
$dictionary = new BloomFilter($storage, 50000, 4, 'dict');

$words = ['php', 'python', 'laravel', 'javascript', 'ruby'];
foreach ($words as $word) {
    $dictionary->insert($word);
}

// Vérification de mots
$tests = ['php', 'java', 'ruby', 'c++'];
foreach ($tests as $word) {
    if ($dictionary->exists($word)) {
        echo "✓ $word existe probablement\n";
    } else {
        echo "✗ $word n'existe pas\n";
    }
}
```

### Cas 3 : Filtrage de spam avec batch

```php
class SpamFilter
{
    private BloomFilter $spamFilter;
    
    public function __construct(BloomFilter $spamFilter)
    {
        $this->spamFilter = $spamFilter;
    }
    
    public function filterComments(array $comments): array
    {
        $collection = new BloomFilterCollection();
        foreach ($comments as $comment) {
            $hash = md5($comment);
            $collection->add(new BloomFilterRecord($hash));
        }
        
        $results = $this->spamFilter->existsBatch($collection);
        
        $filtered = [];
        foreach ($results as $result) {
            if (!$result->exists) {
                $filtered[] = $result->value;
                $this->spamFilter->insert($result->value);
            }
        }
        
        return $filtered;
    }
}
```

## Flux d'exécution

```
insert($value)
    ↓
getBits() → tableau de bits
    ↓
for each hash function (0 → hashCount)
    ↓
    index = hash($value, $i) % size
    ↓
    bits[index] = 1
    ↓
saveBits($bits)
```

```
exists($value)
    ↓
getBits() → tableau de bits
    ↓
for each hash function (0 → hashCount)
    ↓
    index = hash($value, $i) % size
    ↓
    bits[index] === 0? → return false
    ↓
return true
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Aucune exception explicite | - | - |

**Note :** BloomFilter ne lève pas d'exceptions. Les erreurs sont gérées silencieusement par l'utilisation de valeurs par défaut.

## Intégration

### Avec Storage

BloomFilter utilise `StorageInterface` pour la persistance des données :

```php
// Sauvegarde automatique
$bloom->insert('value'); // Persiste dans storage

// Récupération automatique
$bloom = new BloomFilter($storage, 10000, 3, 'bloom'); // Charge depuis storage
```

### Avec les Records

BloomFilter utilise des Records pour représenter les données :

- `BloomFilterRecord` : Représente une valeur à insérer/vérifier
- `BloomFilterResultRecord` : Représente un résultat de vérification

### Avec les Collections

BloomFilter utilise des Collections typées :

- `BloomFilterCollection` : Collection de valeurs
- `BloomFilterResultCollection` : Collection de résultats

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `insert()` | O(k) | k = nombre de fonctions de hachage |
| `exists()` | O(k) | k = nombre de fonctions de hachage |
| `insertBatch()` | O(n*k) | n = nombre d'éléments |
| `existsBatch()` | O(n*k) | n = nombre d'éléments |
| `clear()` | O(1) | Suppression de la clé dans le storage |

**Taux de faux positifs :** `(1 - e^(-k*n/m))^k` où :
- `n` = nombre d'éléments insérés
- `m` = taille du tableau de bits
- `k` = nombre de fonctions de hachage

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| PHP 7.4 | ❌ Non (nécessite PHP 8.0+) |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\AlgoKIT\Algorithms\BloomFilter;
use AndyDefer\AlgoKIT\Collections\BloomFilterCollection;
use AndyDefer\AlgoKIT\Records\BloomFilterRecord;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;

// 1. Initialisation
$storage = new MemoryStorage();
$bloom = new BloomFilter($storage, 1000, 3, 'test_bloom');

// 2. Insertion de valeurs
$values = ['apple', 'banana', 'cherry', 'date', 'elderberry'];
foreach ($values as $value) {
    $bloom->insert($value);
}

// 3. Vérification individuelle
echo "Vérification individuelle:\n";
$tests = ['apple', 'banana', 'grape', 'date', 'fig'];
foreach ($tests as $test) {
    $exists = $bloom->exists($test);
    echo "  '$test': " . ($exists ? '✓ existe' : '✗ n\'existe pas') . "\n";
}

// 4. Vérification par lot
echo "\nVérification par lot:\n";
$collection = new BloomFilterCollection();
$collection->add(new BloomFilterRecord('apple'));
$collection->add(new BloomFilterRecord('grape'));
$collection->add(new BloomFilterRecord('kiwi'));
$collection->add(new BloomFilterRecord('date'));

$results = $bloom->existsBatch($collection);
foreach ($results as $result) {
    echo "  '{$result->value}': " . ($result->exists ? '✓ existe' : '✗ n\'existe pas') . "\n";
}

// 5. Insertion par lot
echo "\nInsertion par lot:\n";
$newValues = new BloomFilterCollection();
$newValues->add(new BloomFilterRecord('grape'));
$newValues->add(new BloomFilterRecord('kiwi'));
$newValues->add(new BloomFilterRecord('lemon'));

$bloom->insertBatch($newValues);
echo "✓ 3 nouvelles valeurs insérées\n";

// 6. Vérification finale
echo "\nVérification finale:\n";
$finalTests = ['apple', 'grape', 'kiwi', 'lemon', 'mango'];
foreach ($finalTests as $test) {
    $exists = $bloom->exists($test);
    echo "  '$test': " . ($exists ? '✓ existe' : '✗ n\'existe pas') . "\n";
}

// 7. Nettoyage
$bloom->clear();
echo "\n✓ Filtre vidé\n";
```

**Sortie attendue :**
```
Vérification individuelle:
  'apple': ✓ existe
  'banana': ✓ existe
  'grape': ✗ n'existe pas
  'date': ✓ existe
  'fig': ✗ n'existe pas

Vérification par lot:
  'apple': ✓ existe
  'grape': ✗ n'existe pas
  'kiwi': ✗ n'existe pas
  'date': ✓ existe

Insertion par lot:
✓ 3 nouvelles valeurs insérées

Vérification finale:
  'apple': ✓ existe
  'grape': ✓ existe
  'kiwi': ✓ existe
  'lemon': ✓ existe
  'mango': ✗ n'existe pas

✓ Filtre vidé
```

## Voir aussi

- `BloomFilterInterface` - Interface du filtre
- `BloomFilterRecord` - Record pour les valeurs
- `BloomFilterResultRecord` - Record pour les résultats
- `BloomFilterCollection` - Collection de valeurs
- `BloomFilterResultCollection` - Collection de résultats
- `StorageInterface` - Interface de persistance
- `MemoryStorage` - Implémentation mémoire du storage