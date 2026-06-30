# TopK - Référence Technique

## Description

TopK est une structure de données qui maintient en mémoire les K éléments les plus fréquents d'un flux de données. Elle permet de suivre en temps réel les tendances et les éléments les plus populaires avec une utilisation mémoire minimale.

## Hiérarchie / Implémentations

```
TopKInterface
    └── TopK
```

**Interfaces implémentées :** `TopKInterface`

## Rôle principal

TopK maintient une liste des K éléments les plus fréquents en utilisant un algorithme efficace qui ne conserve que les éléments pertinents. Particulièrement adapté pour les systèmes de recommandation, l'analyse de tendances, et le suivi des éléments populaires dans des flux massifs de données.

## Installation

```bash
composer require andydefer/algokit
```

## API / Méthodes publiques

### `__construct(StorageInterface $storage, int $k = 10, string $key = 'topk')`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$storage` | `StorageInterface` | Instance du système de stockage |
| `$k` | `int` | Nombre d'éléments à conserver (défaut: 10) |
| `$key` | `string` | Clé d'identification dans le storage (défaut: 'topk') |

**Retourne :** `void`

**Exemple :**
```php
$storage = new MemoryStorage();
$topK = new TopK($storage, 5, 'top_searches');
```

---

### `add(string $value, int $increment = 1): void`

Ajoute une occurrence d'une valeur.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `string` | Valeur à ajouter |
| `$increment` | `int` | Incrément du compteur (défaut: 1) |

**Retourne :** `void`

**Exemple :**
```php
$topK->add('php');
$topK->add('laravel', 3); // Incrémente de 3
```

---

### `getTop(): TopKResultCollection`

Récupère les K éléments les plus fréquents.

**Retourne :** `TopKResultCollection` - Collection des éléments triés par ordre décroissant de fréquence

**Exemple :**
```php
$top = $topK->getTop();
foreach ($top as $item) {
    echo "{$item->value}: {$item->count}\n";
}
```

---

### `addBatch(TopKCollection $collection): void`

Ajoute plusieurs valeurs en lot.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$collection` | `TopKCollection` | Collection de valeurs à ajouter |

**Retourne :** `void`

**Exemple :**
```php
$collection = new TopKCollection();
$collection->add(new TopKRecord('php', 2));
$collection->add(new TopKRecord('laravel', 1));
$topK->addBatch($collection);
```

---

### `clear(): void`

Vide complètement la structure.

**Retourne :** `void`

**Exemple :**
```php
$topK->clear();
```

## Cas d'utilisation

### Cas 1 : Top des termes de recherche

```php
use AndyDefer\AlgoKIT\Algorithms\TopK;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;

$storage = new MemoryStorage();
$topK = new TopK($storage, 5, 'search_trends');

// Simuler des recherches
$searches = [
    'php', 'laravel', 'php', 'javascript', 'php',
    'laravel', 'python', 'php', 'javascript', 'laravel',
    'php', 'go', 'rust', 'php', 'laravel'
];

foreach ($searches as $term) {
    $topK->add($term);
}

$top = $topK->getTop();
echo "Top 5 des termes les plus recherchés:\n";
foreach ($top as $index => $item) {
    echo sprintf("#%d: %s (%d recherches)\n", $index + 1, $item->value, $item->count);
}
```

### Cas 2 : Système de recommandation

```php
use AndyDefer\AlgoKIT\Algorithms\TopK;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;

class RecommendationEngine
{
    private TopK $topK;
    private array $productCatalog = [];
    
    public function __construct(TopK $topK)
    {
        $this->topK = $topK;
    }
    
    public function addProduct(string $id, string $name, array $tags): void
    {
        $this->productCatalog[$id] = [
            'name' => $name,
            'tags' => $tags
        ];
    }
    
    public function trackView(string $userId, string $productId): void
    {
        $key = "{$userId}:{$productId}";
        $this->topK->add($key);
    }
    
    public function getTopProducts(int $limit = 10): array
    {
        $top = $this->topK->getTop();
        $result = [];
        
        foreach ($top as $item) {
            $parts = explode(':', $item->value);
            if (count($parts) === 2) {
                $userId = $parts[0];
                $productId = $parts[1];
                
                if (isset($this->productCatalog[$productId])) {
                    $result[] = [
                        'user_id' => $userId,
                        'product_id' => $productId,
                        'product_name' => $this->productCatalog[$productId]['name'],
                        'views' => $item->count
                    ];
                }
            }
            
            if (count($result) >= $limit) {
                break;
            }
        }
        
        return $result;
    }
    
    public function getUserPreferences(string $userId, int $limit = 5): array
    {
        $top = $this->topK->getTop();
        $preferences = [];
        
        foreach ($top as $item) {
            if (str_starts_with($item->value, $userId . ':')) {
                $productId = explode(':', $item->value)[1];
                if (isset($this->productCatalog[$productId])) {
                    $preferences[] = [
                        'product_id' => $productId,
                        'product_name' => $this->productCatalog[$productId]['name'],
                        'views' => $item->count
                    ];
                }
                
                if (count($preferences) >= $limit) {
                    break;
                }
            }
        }
        
        return $preferences;
    }
}

// Utilisation
$storage = new MemoryStorage();
$topK = new TopK($storage, 20, 'recommendations');
$engine = new RecommendationEngine($topK);

// Ajout de produits
$engine->addProduct('p1', 'Laptop', ['electronics', 'computer']);
$engine->addProduct('p2', 'Smartphone', ['electronics', 'mobile']);
$engine->addProduct('p3', 'Headphones', ['electronics', 'audio']);

// Tracking des vues
$engine->trackView('user_123', 'p1');
$engine->trackView('user_123', 'p1');
$engine->trackView('user_123', 'p2');
$engine->trackView('user_456', 'p1');
$engine->trackView('user_123', 'p3');

// Récupération des préférences
$preferences = $engine->getUserPreferences('user_123');
echo "Préférences de l'utilisateur:\n";
print_r($preferences);

// Récupération des produits populaires
$popular = $engine->getTopProducts(3);
echo "\nProduits les plus populaires:\n";
print_r($popular);
```

### Cas 3 : Analyse de logs en temps réel

```php
class LogAnalyzer
{
    private TopK $topK;
    
    public function __construct(TopK $topK)
    {
        $this->topK = $topK;
    }
    
    public function processLog(array $log): void
    {
        $ip = $log['ip'] ?? 'unknown';
        $endpoint = $log['endpoint'] ?? '/';
        $status = $log['status'] ?? 200;
        
        // Incrémenter différents compteurs
        $this->topK->add("ip:{$ip}");
        $this->topK->add("endpoint:{$endpoint}");
        $this->topK->add("status:{$status}");
    }
    
    public function getTopIPs(int $limit = 5): array
    {
        $top = $this->topK->getTop();
        $result = [];
        
        foreach ($top as $item) {
            if (str_starts_with($item->value, 'ip:')) {
                $ip = substr($item->value, 3);
                $result[] = ['ip' => $ip, 'count' => $item->count];
            }
            
            if (count($result) >= $limit) {
                break;
            }
        }
        
        return $result;
    }
    
    public function getTopEndpoints(int $limit = 5): array
    {
        $top = $this->topK->getTop();
        $result = [];
        
        foreach ($top as $item) {
            if (str_starts_with($item->value, 'endpoint:')) {
                $endpoint = substr($item->value, 9);
                $result[] = ['endpoint' => $endpoint, 'count' => $item->count];
            }
            
            if (count($result) >= $limit) {
                break;
            }
        }
        
        return $result;
    }
    
    public function getStats(): array
    {
        return [
            'total_items' => $this->topK->getTop()->count(),
            'top_ips' => $this->getTopIPs(3),
            'top_endpoints' => $this->getTopEndpoints(3)
        ];
    }
}

// Utilisation
$storage = new MemoryStorage();
$topK = new TopK($storage, 30, 'log_analyzer');
$analyzer = new LogAnalyzer($topK);

$logs = [
    ['ip' => '192.168.1.1', 'endpoint' => '/api/users', 'status' => 200],
    ['ip' => '192.168.1.2', 'endpoint' => '/api/products', 'status' => 200],
    ['ip' => '192.168.1.1', 'endpoint' => '/api/users', 'status' => 200],
    ['ip' => '192.168.1.3', 'endpoint' => '/api/orders', 'status' => 404],
    ['ip' => '192.168.1.1', 'endpoint' => '/api/users', 'status' => 200],
    ['ip' => '192.168.1.2', 'endpoint' => '/api/products', 'status' => 200],
];

foreach ($logs as $log) {
    $analyzer->processLog($log);
}

echo "Statistiques:\n";
print_r($analyzer->getStats());
```

## Flux d'exécution

```
add($value, $increment)
    ↓
getData() → ['items' => [], 'counts' => []]
    ↓
$counts[$value] += $increment
    ↓
$value already in $items?
    ├── Oui → sort and save
    └── Non → count($items) < $k?
         ├── Oui → add to items
         └── Non → find min item
              ↓
         $counts[$value] > $minCount?
              ├── Oui → replace min item
              └── Non → ignore
    ↓
sort items by count (descending)
    ↓
saveData($data)
```

```
getTop()
    ↓
getData() → ['items' => [], 'counts' => []]
    ↓
foreach item in items
    ↓
    add TopKResultRecord($item, $counts[$item])
    ↓
return TopKResultCollection
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Aucune exception explicite | - | - |

**Note :** TopK ne lève pas d'exceptions. Les erreurs sont gérées silencieusement par l'utilisation de valeurs par défaut.

## Intégration

### Avec Storage

TopK utilise `StorageInterface` pour la persistance des données :

```php
// Sauvegarde automatique
$topK->add('value'); // Persiste dans storage

// Récupération automatique
$topK = new TopK($storage, 10, 'topk'); // Charge depuis storage
```

### Avec les Records

TopK utilise des Records pour représenter les données :

- `TopKRecord` : Représente une valeur à ajouter
- `TopKResultRecord` : Représente un résultat de top-k

### Avec les Collections

TopK utilise des Collections typées :

- `TopKCollection` : Collection de valeurs
- `TopKResultCollection` : Collection de résultats

### Avec CountMinSketch

TopK et CountMinSketch peuvent être combinés pour des analyses avancées :

```php
// TopK pour les éléments les plus fréquents
$topK = new TopK($storage, 10, 'top');
$topK->add('php');
$topK->add('php');

// CountMinSketch pour les fréquences exactes approximatives
$cms = new CountMinSketch($storage, 10000, 5, 'freq');
$cms->add('php');
$cms->add('php');

$top = $topK->getTop();
foreach ($top as $item) {
    $estimated = $cms->count($item->value);
    echo "{$item->value}: exact={$item->count}, estimé={$estimated}\n";
}
```

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `add()` | O(k) | k = nombre d'éléments conservés |
| `getTop()` | O(k) | k = nombre d'éléments conservés |
| `addBatch()` | O(n*k) | n = nombre d'éléments, k = éléments conservés |
| `clear()` | O(1) | Suppression de la clé dans le storage |

**Optimisations :**
- L'algorithme maintient une liste triée des K éléments
- Les éléments non pertinents sont rapidement éliminés
- La mémoire utilisée est bornée par K

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

use AndyDefer\AlgoKIT\Algorithms\TopK;
use AndyDefer\AlgoKIT\Collections\TopKCollection;
use AndyDefer\AlgoKIT\Records\TopKRecord;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;

// 1. Initialisation
$storage = new MemoryStorage();
$topK = new TopK($storage, 5, 'test_topk');

echo "TopK avec K = 5\n\n";

// 2. Ajout de valeurs
echo "Ajout de valeurs:\n";
$values = [
    'php', 'laravel', 'php', 'python', 'javascript',
    'php', 'laravel', 'php', 'go', 'rust',
    'php', 'laravel', 'javascript', 'php', 'typescript'
];

foreach ($values as $value) {
    $topK->add($value);
    echo "  + $value\n";
}

echo "\n";

// 3. Récupération du Top K
echo "Top 5 des éléments les plus fréquents:\n";
$top = $topK->getTop();
foreach ($top as $index => $item) {
    echo sprintf("  #%d: %s (%d occurrences)\n", $index + 1, $item->value, $item->count);
}

echo "\n";

// 4. Test avec incréments multiples
echo "Test avec incréments multiples:\n";
$topK->add('python', 5);
$topK->add('rust', 3);
$topK->add('go', 2);

$top = $topK->getTop();
foreach ($top as $index => $item) {
    echo sprintf("  #%d: %s (%d occurrences)\n", $index + 1, $item->value, $item->count);
}

echo "\n";

// 5. Test des opérations batch
echo "Test des opérations batch:\n";
$collection = new TopKCollection();
$collection->add(new TopKRecord('react', 4));
$collection->add(new TopKRecord('vue', 3));
$collection->add(new TopKRecord('angular', 2));

$topK->addBatch($collection);

$top = $topK->getTop();
foreach ($top as $index => $item) {
    echo sprintf("  #%d: %s (%d occurrences)\n", $index + 1, $item->value, $item->count);
}

echo "\n";

// 6. Test de persistance
echo "Test de persistance:\n";
$topK2 = new TopK($storage, 5, 'test_topk');
$top = $topK2->getTop();
foreach ($top as $index => $item) {
    echo sprintf("  #%d: %s (%d occurrences)\n", $index + 1, $item->value, $item->count);
}

echo "\n";

// 7. Test avec différents K
echo "Test avec différents K:\n";
$ks = [3, 5, 10];
foreach ($ks as $k) {
    $testTopK = new TopK($storage, $k, "test_k_{$k}");
    $testData = ['a', 'b', 'c', 'a', 'b', 'a', 'c', 'd', 'e', 'a', 'b'];
    foreach ($testData as $value) {
        $testTopK->add($value);
    }
    
    $top = $testTopK->getTop();
    echo "  K = {$k}: ";
    echo implode(', ', array_map(function($item) {
        return "{$item->value}({$item->count})";
    }, $top->toArray()));
    echo "\n";
}

echo "\n";

// 8. Nettoyage
$topK->clear();
echo "✓ TopK vidé\n";
```

**Sortie attendue :**
```
TopK avec K = 5

Ajout de valeurs:
  + php
  + laravel
  + php
  + python
  + javascript
  + php
  + laravel
  + php
  + go
  + rust
  + php
  + laravel
  + javascript
  + php
  + typescript

Top 5 des éléments les plus fréquents:
  #1: php (6 occurrences)
  #2: laravel (3 occurrences)
  #3: javascript (2 occurrences)
  #4: python (1 occurrences)
  #5: go (1 occurrences)

Test avec incréments multiples:
  #1: php (6 occurrences)
  #2: python (6 occurrences)
  #3: laravel (3 occurrences)
  #4: rust (3 occurrences)
  #5: javascript (2 occurrences)

Test des opérations batch:
  #1: php (6 occurrences)
  #2: python (6 occurrences)
  #3: react (4 occurrences)
  #4: laravel (3 occurrences)
  #5: rust (3 occurrences)

Test de persistance:
  #1: php (6 occurrences)
  #2: python (6 occurrences)
  #3: react (4 occurrences)
  #4: laravel (3 occurrences)
  #5: rust (3 occurrences)

Test avec différents K:
  K = 3: a(4), b(3), c(2)
  K = 5: a(4), b(3), c(2), d(1), e(1)
  K = 10: a(4), b(3), c(2), d(1), e(1)

✓ TopK vidé
```

## Voir aussi

- `TopKInterface` - Interface du TopK
- `TopKRecord` - Record pour les valeurs
- `TopKResultRecord` - Record pour les résultats
- `TopKCollection` - Collection de valeurs
- `TopKResultCollection` - Collection de résultats
- `CountMinSketch` - Structure pour les fréquences approximatives
- `StorageInterface` - Interface de persistance
- `MemoryStorage` - Implémentation mémoire du storage