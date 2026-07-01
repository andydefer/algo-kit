# TopK - Référence Technique

## Description

TopK est une structure de données qui maintient les **K éléments les plus fréquents** dans un flux de données en utilisant un algorithme économe en mémoire. Elle suit en temps réel les éléments qui apparaissent le plus souvent, idéale pour l'analyse de tendances et les classements dynamiques.

## Hiérarchie / Implémentations

```
TopKInterface
    └── TopK (final)
```

La classe implémente l'interface `TopKInterface` et utilise :
- `StorageInterface` pour la persistance des données
- `TopKCollection` pour les opérations batch
- `TopKResultCollection` pour retourner les résultats
- `TopKRecord` pour représenter une valeur à ajouter avec incrément
- `TopKResultRecord` pour représenter un élément du top avec son compteur

## Rôle principal

TopK répond à la question **"Quels sont les K éléments les plus fréquents dans ce flux ?"** en utilisant une mémoire constante indépendante du nombre d'éléments. Lorsqu'un nouvel élément est ajouté et que la liste des K éléments est pleine, l'élément le moins fréquent est remplacé si le nouvel élément a une fréquence supérieure.

**Propriétés fondamentales :**
- ✅ **Mémoire constante** : Ne stocke que K éléments, quel que soit le volume de données
- ✅ **Temps constant** : Les opérations sont en O(K) pour la recherche du minimum
- ✅ **Suivi continu** : Idéal pour les flux de données en temps réel
- ✅ **Persistance** : Peut être sauvegardé et restauré
- ⚠️ **Approximatif** : Peut manquer des éléments si K est trop petit

## Installation

```bash
composer require andydefer/algo-kit
```

Prérequis :
- PHP 8.1 ou supérieur
- Extension `storage-kit` installée

## API / Méthodes publiques

### `__construct(StorageInterface $storage, int $k = 10, string $key = 'topk')`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$storage` | `StorageInterface` | Backend de stockage pour la persistance |
| `$k` | `int` | Nombre d'éléments les plus fréquents à suivre |
| `$key` | `string` | Clé unique identifiant l'instance (défaut : 'topk') |

**Retourne :** `void`

**Exemple :**
```php
$storage = new MemoryStorage();
$topK = new TopK($storage, 5, 'trending_words');
```

---

### `add(string $value, int $increment = 1): void`

Ajoute une valeur avec un incrément optionnel.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `string` | La valeur à ajouter |
| `$increment` | `int` | Montant de l'incrémentation (défaut : 1) |

**Retourne :** `void`

**Exemple :**
```php
$topK->add('php');
$topK->add('php', 5); // Incrémente de 5
$topK->add('laravel');
```

---

### `getTop(): TopKResultCollection`

Retourne les K éléments les plus fréquents.

**Retourne :** `TopKResultCollection` - Collection des éléments avec leurs compteurs

**Exemple :**
```php
$results = $topK->getTop();
foreach ($results as $result) {
    echo "{$result->value}: {$result->count}\n";
}
```

---

### `addBatch(TopKCollection $collection): void`

Ajoute plusieurs valeurs en lot.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$collection` | `TopKCollection` | Collection de valeurs à ajouter avec leurs incréments |

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

Supprime toutes les données du tracker TopK.

**Retourne :** `void`

**Exemple :**
```php
$topK->clear(); // Réinitialise complètement
```

---

## Cas d'utilisation

### Cas 1 : Tendances des mots-clés dans les recherches

```php
<?php

declare(strict_types=1);

use AndyDefer\AlgoKIT\Algorithms\TopK;
use AndyDefer\StorageKit\Storage\MemoryStorage;

class TrendingSearch
{
    private TopK $topK;
    
    public function __construct(TopK $topK)
    {
        $this->topK = $topK;
    }
    
    public function trackSearch(string $query): void
    {
        $this->topK->add($query);
    }
    
    public function getTopSearches(int $limit = 5): array
    {
        $results = [];
        foreach ($this->topK->getTop() as $result) {
            if (count($results) >= $limit) break;
            $results[] = [
                'query' => $result->value,
                'count' => $result->count
            ];
        }
        return $results;
    }
}

// Utilisation
$storage = new MemoryStorage();
$topK = new TopK($storage, 10, 'trending_search');
$trending = new TrendingSearch($topK);

// Simuler des recherches
$searches = [
    'php', 'laravel', 'php', 'python', 'php', 
    'javascript', 'python', 'php', 'laravel', 
    'golang', 'php', 'python', 'ruby'
];

foreach ($searches as $query) {
    $trending->trackSearch($query);
}

echo "🏆 Tendances des recherches :\n";
foreach ($trending->getTopSearches(5) as $rank => $item) {
    echo "  #" . ($rank + 1) . " {$item['query']} ({$item['count']} fois)\n";
}
// Sortie :
// 🏆 Tendances des recherches :
//   #1 php (5 fois)
//   #2 python (3 fois)
//   #3 laravel (2 fois)
//   #4 javascript (1 fois)
//   #5 golang (1 fois)
```

### Cas 2 : Produits les plus consultés dans un e-commerce

```php
<?php

declare(strict_types=1);

use AndyDefer\AlgoKIT\Algorithms\TopK;
use AndyDefer\AlgoKIT\Collections\TopKCollection;
use AndyDefer\AlgoKIT\Records\TopKRecord;
use AndyDefer\StorageKit\Storage\MemoryStorage;

class ProductViews
{
    private TopK $topK;
    
    public function __construct(TopK $topK)
    {
        $this->topK = $topK;
    }
    
    public function viewProduct(string $productId): void
    {
        $this->topK->add($productId);
    }
    
    public function viewProductsBatch(array $productIds): void
    {
        $collection = new TopKCollection();
        foreach ($productIds as $productId) {
            $collection->add(new TopKRecord($productId, 1));
        }
        $this->topK->addBatch($collection);
    }
    
    public function getTopProducts(int $limit = 5): array
    {
        $top = [];
        foreach ($this->topK->getTop() as $result) {
            if (count($top) >= $limit) break;
            $top[] = [
                'product_id' => $result->value,
                'views' => $result->count
            ];
        }
        return $top;
    }
}

// Utilisation
$storage = new MemoryStorage();
$topK = new TopK($storage, 20, 'product_views');
$productTracker = new ProductViews($topK);

// Simuler des vues de produits
$views = [
    'p1', 'p2', 'p1', 'p3', 'p1', 'p4', 'p2', 'p1', 'p5', 'p3',
    'p1', 'p2', 'p1', 'p3', 'p1', 'p6', 'p2', 'p1', 'p7', 'p3'
];

// Ajout en batch
$productTracker->viewProductsBatch($views);

echo "🛍️ Produits les plus consultés :\n";
foreach ($productTracker->getTopProducts(5) as $rank => $product) {
    $stars = str_repeat('⭐', min(5, ceil($product['views'] / 2)));
    echo "  #" . ($rank + 1) . " Produit {$product['product_id']} : {$product['views']} vues $stars\n";
}
// Sortie :
// 🛍️ Produits les plus consultés :
//   #1 Produit p1 : 7 vues ⭐⭐⭐⭐
//   #2 Produit p2 : 4 vues ⭐⭐
//   #3 Produit p3 : 4 vues ⭐⭐
//   #4 Produit p4 : 1 vue ⭐
//   #5 Produit p5 : 1 vue ⭐
```

### Cas 3 : Analyse de logs d'erreurs

```php
<?php

declare(strict_types=1);

use AndyDefer\AlgoKIT\Algorithms\TopK;
use AndyDefer\StorageKit\Storage\MemoryStorage;

class ErrorAnalyzer
{
    private TopK $topK;
    
    public function __construct(TopK $topK)
    {
        $this->topK = $topK;
    }
    
    public function logError(string $errorType, string $message): void
    {
        $this->topK->add($errorType);
        // On peut aussi tracker les messages d'erreur complets
        $this->topK->add("{$errorType}: {$message}");
    }
    
    public function getTopErrors(int $limit = 5): array
    {
        $errors = [];
        foreach ($this->topK->getTop() as $result) {
            if (count($errors) >= $limit) break;
            $errors[] = [
                'error' => $result->value,
                'count' => $result->count
            ];
        }
        return $errors;
    }
}

// Utilisation
$storage = new MemoryStorage();
$topK = new TopK($storage, 15, 'error_analysis');
$analyzer = new ErrorAnalyzer($topK);

// Simuler des erreurs
$errors = [
    ['404', 'Page not found'],
    ['500', 'Internal Server Error'],
    ['404', 'Page not found'],
    ['403', 'Forbidden'],
    ['404', 'Page not found'],
    ['500', 'Internal Server Error'],
    ['429', 'Too Many Requests'],
    ['404', 'Page not found'],
    ['403', 'Forbidden'],
    ['404', 'Page not found'],
];

foreach ($errors as [$type, $message]) {
    $analyzer->logError($type, $message);
}

echo "🐞 Top erreurs :\n";
foreach ($analyzer->getTopErrors(3) as $rank => $error) {
    $icon = ['🔴', '🟡', '🟠'][$rank] ?? '📊';
    echo "  $icon {$error['error']} : {$error['count']} occurrences\n";
}
// Sortie :
// 🐞 Top erreurs :
//   🔴 404 : 5 occurrences
//   🟡 500 : 2 occurrences
//   🟠 403 : 2 occurrences
```

### Cas 4 : Suivi des artistes les plus écoutés

```php
<?php

declare(strict_types=1);

use AndyDefer\AlgoKIT\Algorithms\TopK;
use AndyDefer\StorageKit\Storage\MemoryStorage;

class MusicAnalytics
{
    private TopK $topK;
    private int $listens = 0;
    
    public function __construct(TopK $topK)
    {
        $this->topK = $topK;
    }
    
    public function playSong(string $artist, string $song): void
    {
        $this->topK->add($artist);
        $this->topK->add("{$artist} - {$song}");
        $this->listens++;
    }
    
    public function getTopArtists(int $limit = 3): array
    {
        $artists = [];
        foreach ($this->topK->getTop() as $result) {
            if (count($artists) >= $limit) break;
            // Filtrer pour ne garder que les artistes (pas les titres)
            if (strpos($result->value, ' - ') === false) {
                $artists[] = [
                    'name' => $result->value,
                    'plays' => $result->count,
                    'percentage' => round(($result->count / $this->listens) * 100, 1)
                ];
            }
        }
        return $artists;
    }
    
    public function getTopSongs(int $limit = 3): array
    {
        $songs = [];
        foreach ($this->topK->getTop() as $result) {
            if (count($songs) >= $limit) break;
            if (strpos($result->value, ' - ') !== false) {
                $songs[] = [
                    'title' => $result->value,
                    'plays' => $result->count
                ];
            }
        }
        return $songs;
    }
}

// Utilisation
$storage = new MemoryStorage();
$topK = new TopK($storage, 20, 'music_analytics');
$music = new MusicAnalytics($topK);

$plays = [
    ['The Beatles', 'Hey Jude'],
    ['Queen', 'Bohemian Rhapsody'],
    ['The Beatles', 'Let It Be'],
    ['Queen', 'We Will Rock You'],
    ['The Beatles', 'Hey Jude'],
    ['Queen', 'Bohemian Rhapsody'],
    ['The Beatles', 'Hey Jude'],
    ['Pink Floyd', 'Comfortably Numb'],
    ['Queen', 'Bohemian Rhapsody'],
    ['The Beatles', 'Hey Jude'],
];

foreach ($plays as [$artist, $song]) {
    $music->playSong($artist, $song);
}

echo "🎵 Top artistes :\n";
foreach ($music->getTopArtists(3) as $rank => $artist) {
    echo "  #" . ($rank + 1) . " {$artist['name']} : {$artist['plays']} écoutes ({$artist['percentage']}%)\n";
}

echo "\n🎶 Top chansons :\n";
foreach ($music->getTopSongs(3) as $rank => $song) {
    echo "  #" . ($rank + 1) . " {$song['title']} : {$song['plays']} écoutes\n";
}
// Sortie :
// 🎵 Top artistes :
//   #1 The Beatles : 4 écoutes (40%)
//   #2 Queen : 3 écoutes (30%)
//   #3 Pink Floyd : 1 écoute (10%)
// 
// 🎶 Top chansons :
//   #1 The Beatles - Hey Jude : 4 écoutes
//   #2 Queen - Bohemian Rhapsody : 3 écoutes
//   #3 The Beatles - Let It Be : 1 écoute
```

## Flux d'exécution

### Ajout d'une valeur

```
add($value, $increment)
    ↓
getData() → Récupérer les données du storage
    ↓
incrementCount($counts, $value, $increment) → Incrémenter le compteur
    ↓
isValueTracked($items, $value) → L'élément est-il suivi ?
    ├── OUI → Passer
    └── NON → addOrReplaceItem($items, $counts, $value)
                ↓
        hasRoomForMoreItems($items) → Place disponible ?
            ├── OUI → Ajouter $value aux items
            └── NON → replaceLeastFrequentItem($items, $counts, $value)
                        ↓
                leastFrequent = findLeastFrequentItem($items, $counts)
                        ↓
                count($value) > count(leastFrequent) ?
                        ↓
                    OUI → Remplacer leastFrequent par $value
                    NON → Ne rien faire
    ↓
sortItemsByCount($items, $counts) → Trier par ordre décroissant
    ↓
saveData($data) → Persister dans le storage
```

### Récupération des Top K

```
getTop()
    ↓
getData() → Récupérer les données du storage
    ↓
Pour chaque item dans items :
    results->add(new TopKResultRecord(item, counts[item]))
    ↓
Retourner results
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Aucune | - | - |

**Note :** La classe ne lève pas d'exceptions directement. Les erreurs peuvent provenir de l'implémentation de `StorageInterface` utilisée.

## Intégration

### Avec StorageKit

```php
use AndyDefer\StorageKit\Storage\MemoryStorage;
use AndyDefer\StorageKit\Storage\CacheStorage;
use AndyDefer\AlgoKIT\Algorithms\TopK;

// Stockage en mémoire (pour les tests)
$memoryStorage = new MemoryStorage();
$topK = new TopK($memoryStorage);

// Stockage persistant avec cache
$cacheStorage = new CacheStorage('redis');
$topK = new TopK($cacheStorage, 20, 'production_topk');
```

### Avec les collections

```php
use AndyDefer\AlgoKIT\Collections\TopKCollection;
use AndyDefer\AlgoKIT\Collections\TopKResultCollection;
use AndyDefer\AlgoKIT\Records\TopKRecord;
use AndyDefer\AlgoKIT\Records\TopKResultRecord;

// Créer une collection de valeurs avec incréments
$collection = new TopKCollection();
$collection->add(new TopKRecord('php', 2));
$collection->add(new TopKRecord('laravel', 1));
$collection->add(new TopKRecord('python', 1));

// Ajout en batch
$topK->addBatch($collection);

// Récupérer les résultats
$results = $topK->getTop();

// Filtrer pour obtenir les éléments avec > 1 occurrence
$frequent = $results->filter(
    fn(TopKResultRecord $r) => $r->count > 1
);
```

### Avec les autres algorithmes

TopK peut être combiné avec d'autres algorithmes probabilistes :

- **Avec CountMinSketch** : Obtenir des fréquences plus précises
- **Avec BloomFilter** : Vérifier l'appartenance des éléments
- **Avec HyperLogLog** : Compter les éléments uniques dans le top

```php
use AndyDefer\AlgoKIT\Algorithms\TopK;
use AndyDefer\AlgoKIT\Algorithms\CountMinSketch;

$topK = new TopK($storage, 10, 'top');
$cms = new CountMinSketch($storage, 10000, 5, 'frequencies');

// Suivre les éléments fréquents
$items = ['php', 'laravel', 'php', 'python', 'laravel', 'php'];
foreach ($items as $item) {
    $topK->add($item);
    $cms->add($item);
}

// Obtenir des fréquences plus précises pour les top éléments
foreach ($topK->getTop() as $result) {
    $exact = $cms->count($result->value);
    echo "{$result->value}: top={$result->count}, cms={$exact}\n";
}
```

## Performance

| Opération | Complexité | Description |
|-----------|------------|-------------|
| `add()` | O(K) | Recherche du minimum parmi K éléments |
| `getTop()` | O(1) | Lecture directe (déjà trié) |
| `addBatch()` | O(n × K) | n = nombre d'éléments |
| `clear()` | O(1) | Suppression de la clé en storage |

**Caractéristiques :**
- **Mémoire constante** : Utilise toujours K × 2 entrées
- **Temps de mise à jour** : O(K) pour trouver le minimum
- **Scalabilité** : Indépendant du nombre total d'éléments

**Recommandations :**

| Usage | K recommandé | Description |
|-------|--------------|-------------|
| Petits flux | 5-10 | Tendances simples |
| Flux moyens | 20-50 | Classements détaillés |
| Grands flux | 50-100 | Analyses approfondies |
| Très grands flux | 100-500 | Monitoring temps réel |

## Compatibilité

| Version | Support | Notes |
|---------|---------|-------|
| PHP 8.1+ | ✅ Complet | Types et syntaxe recommandés |
| PHP 8.0 | ✅ Complet | Compatible avec ajustements mineurs |
| PHP 7.4 | ❌ Non supporté | Utilise `fn()` et `readonly` |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\AlgoKIT\Algorithms\TopK;
use AndyDefer\AlgoKIT\Collections\TopKCollection;
use AndyDefer\AlgoKIT\Records\TopKRecord;
use AndyDefer\StorageKit\Storage\MemoryStorage;

// 1. Initialisation
$storage = new MemoryStorage();
$topK = new TopK($storage, 3, 'demo_topk');

echo "🏆 DÉMONSTRATION TOP-K\n";
echo "═════════════════════════\n\n";

// 2. Ajout de données
echo "📝 Ajout de données :\n";
$data = ['php', 'laravel', 'php', 'python', 'php', 'laravel', 'golang', 'php'];

foreach ($data as $item) {
    $topK->add($item);
    echo "  + $item\n";
}

// 3. Affichage du top initial
echo "\n📊 Top initial (K=3) :\n";
foreach ($topK->getTop() as $rank => $result) {
    echo "  #" . ($rank + 1) . " {$result->value} : {$result->count}\n";
}

// 4. Ajout avec incréments
echo "\n⬆️ Ajout avec incréments :\n";
$topK->add('javascript', 3);
$topK->add('python', 2);
$topK->add('ruby', 1);
echo "  + javascript (x3)\n";
echo "  + python (x2)\n";
echo "  + ruby (x1)\n";

// 5. Affichage du top mis à jour
echo "\n📊 Top après incréments :\n";
foreach ($topK->getTop() as $rank => $result) {
    $bar = str_repeat('█', min(20, $result->count * 4));
    echo "  #" . ($rank + 1) . " {$result->value} : {$result->count} $bar\n";
}

// 6. Opérations batch
echo "\n📦 Opérations batch :\n";
$batch = new TopKCollection();
$batch->add(new TopKRecord('javascript', 5));
$batch->add(new TopKRecord('php', 10));
$batch->add(new TopKRecord('golang', 2));

$topK->addBatch($batch);
echo "  ✓ Batch effectué\n";

// 7. Affichage final
echo "\n🏁 Top final (K=3) :\n";
foreach ($topK->getTop() as $rank => $result) {
    $emoji = ['🥇', '🥈', '🥉'][$rank] ?? '🏅';
    echo "  $emoji {$result->value} : {$result->count}\n";
}

// 8. Nettoyage
echo "\n🧹 Nettoyage...\n";
$topK->clear();
echo "  ✓ TopK vidé\n";

$empty = $topK->getTop();
echo "  Éléments restants : " . count($empty) . "\n";

// Exemple de sortie :
// 🏆 DÉMONSTRATION TOP-K
// ═════════════════════════
// 
// 📝 Ajout de données :
//   + php
//   + laravel
//   + php
//   + python
//   + php
//   + laravel
//   + golang
//   + php
// 
// 📊 Top initial (K=3) :
//   #1 php : 4
//   #2 laravel : 2
//   #3 python : 1
// 
// ⬆️ Ajout avec incréments :
//   + javascript (x3)
//   + python (x2)
//   + ruby (x1)
// 
// 📊 Top après incréments :
//   #1 php : 4 ████████████████████
//   #2 javascript : 3 ████████████
//   #3 python : 3 ████████████
// 
// 📦 Opérations batch :
//   ✓ Batch effectué
// 
// 🏁 Top final (K=3) :
//   🥇 php : 14
//   🥈 javascript : 8
//   🥉 python : 3
// 
// 🧹 Nettoyage...
//   ✓ TopK vidé
//   Éléments restants : 0
```

## Voir aussi

- [`count-min-sketch`](count-min-sketch.md) - Compteur probabiliste de fréquences
- [`bloom-filter`](bloom-filter.md) - Test probabiliste d'appartenance
- [`hyper-log-log`](hyper-log-log.md) - Estimation de cardinalité
- [`bk-tree`](bk-tree.md) - Recherche floue par distance de Levenshtein
- [`trie`](trie.md) - Recherche par préfixe