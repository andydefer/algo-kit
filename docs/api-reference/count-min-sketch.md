# CountMinSketch - Référence Technique

## Description

CountMinSketch est une structure de données probabiliste qui estime la fréquence d'apparition des éléments dans un flux de données. Elle permet de compter approximativement le nombre d'occurrences de chaque valeur tout en utilisant très peu de mémoire.

## Hiérarchie / Implémentations

```
CountMinSketchInterface
    └── CountMinSketch
```

**Interfaces implémentées :** `CountMinSketchInterface`

## Rôle principal

CountMinSketch utilise une matrice de compteurs et plusieurs fonctions de hachage pour enregistrer les fréquences des éléments. Chaque insertion incrémente plusieurs compteurs, et la fréquence estimée est le minimum des compteurs correspondants. Particulièrement adapté pour l'analyse de flux massifs de données où la mémoire est limitée.

## Installation

```bash
composer require andydefer/algokit
```

## API / Méthodes publiques

### `__construct(StorageInterface $storage, int $width = 10000, int $depth = 5, string $key = 'cms')`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$storage` | `StorageInterface` | Instance du système de stockage |
| `$width` | `int` | Largeur de la table (défaut: 10000) |
| `$depth` | `int` | Profondeur / nombre de fonctions de hachage (défaut: 5) |
| `$key` | `string` | Clé d'identification dans le storage (défaut: 'cms') |

**Retourne :** `void`

**Exemple :**
```php
$storage = new MemoryStorage();
$cms = new CountMinSketch($storage, 10000, 5, 'search_frequencies');
```

---

### `add(string $value): void`

Ajoute une occurrence d'une valeur.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `string` | Valeur à compter |

**Retourne :** `void`

**Exemple :**
```php
$cms->add('laravel');
$cms->add('laravel');
$cms->add('php');
// 'laravel' a maintenant 2 occurrences, 'php' en a 1
```

---

### `count(string $value): int`

Estime la fréquence d'une valeur.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `string` | Valeur à compter |

**Retourne :** `int` - Estimation du nombre d'occurrences

**Exemple :**
```php
$freq = $cms->count('laravel'); // Retourne approximativement le nombre d'occurrences
```

---

### `addBatch(CountMinSketchCollection $collection): void`

Ajoute plusieurs valeurs en lot.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$collection` | `CountMinSketchCollection` | Collection de valeurs à ajouter |

**Retourne :** `void`

**Exemple :**
```php
$collection = new CountMinSketchCollection();
$collection->add(new CountMinSketchRecord('php'));
$collection->add(new CountMinSketchRecord('php'));
$collection->add(new CountMinSketchRecord('laravel'));
$cms->addBatch($collection);
```

---

### `countBatch(CountMinSketchCollection $collection): CountMinSketchResultCollection`

Compte plusieurs valeurs en lot.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$collection` | `CountMinSketchCollection` | Collection de valeurs à compter |

**Retourne :** `CountMinSketchResultCollection` - Collection des résultats avec les fréquences

**Exemple :**
```php
$results = $cms->countBatch($collection);
foreach ($results as $result) {
    echo "{$result->value}: {$result->count}\n";
}
```

---

### `clear(): void`

Vide complètement le sketch.

**Retourne :** `void`

**Exemple :**
```php
$cms->clear();
```

## Cas d'utilisation

### Cas 1 : Analyse des termes de recherche

```php
use AndyDefer\AlgoKIT\Algorithms\CountMinSketch;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;

$storage = new MemoryStorage();
$cms = new CountMinSketch($storage, 100000, 5, 'search_terms');

// Simuler des recherches utilisateurs
$searches = ['php', 'laravel', 'php', 'javascript', 'php', 'laravel', 'python'];

foreach ($searches as $term) {
    $cms->add($term);
}

echo "Fréquence de 'php': " . $cms->count('php') . "\n";      // ~3
echo "Fréquence de 'laravel': " . $cms->count('laravel') . "\n"; // ~2
echo "Fréquence de 'javascript': " . $cms->count('javascript') . "\n"; // ~1
```

### Cas 2 : Analyse de logs d'accès avec TopK

```php
use AndyDefer\AlgoKIT\Algorithms\CountMinSketch;
use AndyDefer\AlgoKIT\Algorithms\TopK;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;
use AndyDefer\AlgoKIT\Collections\CountMinSketchCollection;
use AndyDefer\AlgoKIT\Records\CountMinSketchRecord;

class LogAnalyzer
{
    private CountMinSketch $cms;
    private TopK $topK;
    
    public function __construct(CountMinSketch $cms, TopK $topK)
    {
        $this->cms = $cms;
        $this->topK = $topK;
    }
    
    public function processLogs(array $logs): void
    {
        $collection = new CountMinSketchCollection();
        
        foreach ($logs as $log) {
            $ip = $log['ip'] ?? 'unknown';
            $collection->add(new CountMinSketchRecord($ip));
            $this->topK->add($ip);
        }
        
        $this->cms->addBatch($collection);
    }
    
    public function getIPFrequency(string $ip): int
    {
        return $this->cms->count($ip);
    }
    
    public function getTopIPs(int $limit = 10): array
    {
        $top = $this->topK->getTop();
        $result = [];
        
        foreach ($top as $item) {
            $result[] = [
                'ip' => $item->value,
                'count' => $item->count,
                'estimated' => $this->cms->count($item->value)
            ];
        }
        
        return array_slice($result, 0, $limit);
    }
}

// Utilisation
$storage = new MemoryStorage();
$cms = new CountMinSketch($storage, 10000, 5, 'log_analyzer');
$topK = new TopK($storage, 10, 'top_ips');

$analyzer = new LogAnalyzer($cms, $topK);

$logs = [
    ['ip' => '192.168.1.100', 'time' => '2024-01-01 10:00:00'],
    ['ip' => '192.168.1.101', 'time' => '2024-01-01 10:01:00'],
    ['ip' => '192.168.1.100', 'time' => '2024-01-01 10:02:00'],
    ['ip' => '192.168.1.102', 'time' => '2024-01-01 10:03:00'],
    ['ip' => '192.168.1.100', 'time' => '2024-01-01 10:04:00'],
];

$analyzer->processLogs($logs);

echo "Fréquence de 192.168.1.100: " . $analyzer->getIPFrequency('192.168.1.100') . "\n";
echo "Top IPs:\n";
print_r($analyzer->getTopIPs(3));
```

### Cas 3 : Système de recommandation complet

```php
use AndyDefer\AlgoKIT\Algorithms\CountMinSketch;
use AndyDefer\AlgoKIT\Algorithms\TopK;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;

class RecommendationSystem
{
    private CountMinSketch $cms;
    private TopK $topK;
    private array $productCatalog = [];
    
    public function __construct(CountMinSketch $cms, TopK $topK)
    {
        $this->cms = $cms;
        $this->topK = $topK;
    }
    
    public function addProduct(string $productId, string $name, array $tags): void
    {
        $this->productCatalog[$productId] = [
            'name' => $name,
            'tags' => $tags
        ];
    }
    
    public function trackView(string $userId, string $productId): void
    {
        $key = "{$userId}:{$productId}";
        $this->cms->add($key);
        $this->topK->add($key);
    }
    
    public function getUserInterests(string $userId, int $limit = 5): array
    {
        $top = $this->topK->getTop();
        $interests = [];
        
        foreach ($top as $item) {
            if (str_starts_with($item->value, $userId . ':')) {
                $productId = explode(':', $item->value)[1];
                $frequency = $this->cms->count($item->value);
                
                if (isset($this->productCatalog[$productId])) {
                    $interests[] = [
                        'product_id' => $productId,
                        'product_name' => $this->productCatalog[$productId]['name'],
                        'views' => $frequency,
                        'tags' => $this->productCatalog[$productId]['tags']
                    ];
                }
                
                if (count($interests) >= $limit) {
                    break;
                }
            }
        }
        
        return $interests;
    }
    
    public function getRecommendations(string $userId, int $limit = 5): array
    {
        $interests = $this->getUserInterests($userId, 3);
        $tags = [];
        
        foreach ($interests as $interest) {
            $tags = array_merge($tags, $interest['tags']);
        }
        
        $tagCounts = [];
        foreach ($tags as $tag) {
            if (!isset($tagCounts[$tag])) {
                $tagCounts[$tag] = 0;
            }
            $tagCounts[$tag]++;
        }
        
        arsort($tagCounts);
        $topTags = array_slice(array_keys($tagCounts), 0, 3);
        
        $recommendations = [];
        foreach ($this->productCatalog as $productId => $product) {
            if (array_intersect($product['tags'], $topTags)) {
                $recommendations[] = [
                    'product_id' => $productId,
                    'product_name' => $product['name'],
                    'matching_tags' => array_intersect($product['tags'], $topTags)
                ];
            }
            
            if (count($recommendations) >= $limit) {
                break;
            }
        }
        
        return $recommendations;
    }
}

// Utilisation
$storage = new MemoryStorage();
$cms = new CountMinSketch($storage, 10000, 5, 'recommendations');
$topK = new TopK($storage, 20, 'top_products');

$recommender = new RecommendationSystem($cms, $topK);

// Ajout de produits
$recommender->addProduct('p1', 'Laptop', ['electronics', 'computer', 'gaming']);
$recommender->addProduct('p2', 'Smartphone', ['electronics', 'mobile', 'communication']);
$recommender->addProduct('p3', 'Headphones', ['electronics', 'audio', 'music']);
$recommender->addProduct('p4', 'Book PHP', ['programming', 'php', 'web']);
$recommender->addProduct('p5', 'Book Python', ['programming', 'python', 'ai']);

// Tracking des vues
$recommender->trackView('user_123', 'p1');
$recommender->trackView('user_123', 'p1');
$recommender->trackView('user_123', 'p2');
$recommender->trackView('user_123', 'p4');

// Récupération des intérêts
$interests = $recommender->getUserInterests('user_123');
echo "Intérêts de l'utilisateur:\n";
print_r($interests);

// Récupération des recommandations
$recommendations = $recommender->getRecommendations('user_123');
echo "\nRecommandations:\n";
print_r($recommendations);
```

## Flux d'exécution

```
add($value)
    ↓
getTable() → matrice de compteurs
    ↓
for each hash function (0 → depth)
    ↓
    index = hash($value, $i) % width
    ↓
    table[$i][$index]++
    ↓
saveTable($table)
```

```
count($value)
    ↓
getTable() → matrice de compteurs
    ↓
min = PHP_INT_MAX
    ↓
for each hash function (0 → depth)
    ↓
    index = hash($value, $i) % width
    ↓
    min = min(min, table[$i][$index])
    ↓
return min
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Aucune exception explicite | - | - |

**Note :** CountMinSketch ne lève pas d'exceptions. Les erreurs sont gérées silencieusement par l'utilisation de valeurs par défaut.

## Intégration

### Avec Storage

CountMinSketch utilise `StorageInterface` pour la persistance des données :

```php
// Sauvegarde automatique
$cms->add('value'); // Persiste dans storage

// Récupération automatique
$cms = new CountMinSketch($storage, 10000, 5, 'cms'); // Charge depuis storage
```

### Avec les Records

CountMinSketch utilise des Records pour représenter les données :

- `CountMinSketchRecord` : Représente une valeur à compter
- `CountMinSketchResultRecord` : Représente un résultat de comptage

### Avec les Collections

CountMinSketch utilise des Collections typées :

- `CountMinSketchCollection` : Collection de valeurs
- `CountMinSketchResultCollection` : Collection de résultats

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `add()` | O(depth) | depth = nombre de fonctions de hachage |
| `count()` | O(depth) | depth = nombre de fonctions de hachage |
| `addBatch()` | O(n*depth) | n = nombre d'éléments |
| `countBatch()` | O(n*depth) | n = nombre d'éléments |
| `clear()` | O(1) | Suppression de la clé dans le storage |

**Précision :** L'erreur est bornée par `(width / 2) * depth` avec une probabilité de 1 - e^(-depth).

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

use AndyDefer\AlgoKIT\Algorithms\CountMinSketch;
use AndyDefer\AlgoKIT\Algorithms\TopK;
use AndyDefer\AlgoKIT\Collections\CountMinSketchCollection;
use AndyDefer\AlgoKIT\Records\CountMinSketchRecord;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;

// 1. Initialisation
$storage = new MemoryStorage();
$cms = new CountMinSketch($storage, 1000, 3, 'test_cms');
$topK = new TopK($storage, 10, 'test_topk');

// 2. Ajout de valeurs
echo "Ajout de valeurs:\n";
$values = ['apple', 'banana', 'apple', 'cherry', 'banana', 'apple', 'date'];

foreach ($values as $value) {
    $cms->add($value);
    $topK->add($value);
    echo "  + $value\n";
}

// 3. Comptage individuel
echo "\nComptage individuel:\n";
$testValues = ['apple', 'banana', 'cherry', 'date', 'elderberry'];
foreach ($testValues as $value) {
    $count = $cms->count($value);
    $topCount = $topK->getTop()->toArray();
    echo "  '$value': $count\n";
}

// 4. Comptage par lot
echo "\nComptage par lot:\n";
$collection = new CountMinSketchCollection();
$collection->add(new CountMinSketchRecord('apple'));
$collection->add(new CountMinSketchRecord('banana'));
$collection->add(new CountMinSketchRecord('cherry'));
$collection->add(new CountMinSketchRecord('elderberry'));

$results = $cms->countBatch($collection);
foreach ($results as $result) {
    echo "  '{$result->value}': {$result->count}\n";
}

// 5. Ajout par lot
echo "\nAjout par lot:\n";
$newValues = new CountMinSketchCollection();
$newValues->add(new CountMinSketchRecord('elderberry'));
$newValues->add(new CountMinSketchRecord('elderberry'));
$newValues->add(new CountMinSketchRecord('fig'));

$cms->addBatch($newValues);
foreach ($newValues as $record) {
    $topK->add($record->value);
}
echo "✓ 3 nouvelles occurrences ajoutées\n";

// 6. Vérification finale
echo "\nVérification finale:\n";
$finalTests = ['apple', 'banana', 'elderberry', 'fig', 'grape'];
foreach ($finalTests as $value) {
    $count = $cms->count($value);
    echo "  '$value': $count\n";
}

// 7. Top K avec CountMinSketch
echo "\nTop K des valeurs les plus fréquentes:\n";
$topItems = $topK->getTop();
foreach ($topItems as $item) {
    $estimated = $cms->count($item->value);
    echo "  {$item->value}: exact={$item->count}, estimé={$estimated}\n";
}

// 8. Nettoyage
$cms->clear();
$topK->clear();
echo "\n✓ Structures vidées\n";
```

## Voir aussi

- `CountMinSketchInterface` - Interface du sketch
- `CountMinSketchRecord` - Record pour les valeurs
- `CountMinSketchResultRecord` - Record pour les résultats
- `CountMinSketchCollection` - Collection de valeurs
- `CountMinSketchResultCollection` - Collection de résultats
- `TopK` - Structure pour les éléments les plus fréquents
- `StorageInterface` - Interface de persistance
- `MemoryStorage` - Implémentation mémoire du storage