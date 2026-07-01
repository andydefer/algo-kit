# CountMinSketch - Référence Technique

## Description

Le CountMinSketch est une structure de données probabiliste permettant d'estimer la fréquence d'apparition des éléments dans un flux de données. Elle utilise une matrice de compteurs et plusieurs fonctions de hachage pour enregistrer les fréquences avec une mémoire sub-linéaire.

## Hiérarchie / Implémentations

```
CountMinSketchInterface
    └── CountMinSketch (final)
```

La classe implémente l'interface `CountMinSketchInterface` et utilise :
- `StorageInterface` pour la persistance des données
- `CountMinSketchCollection` pour les opérations batch
- `CountMinSketchResultCollection` pour retourner les résultats
- `CountMinSketchRecord` pour représenter une valeur à compter
- `CountMinSketchResultRecord` pour représenter un résultat de comptage

## Rôle principal

Le CountMinSketch répond à la question **"Combien de fois cet élément est-il apparu ?"** dans un flux de données massif. Il maintient une matrice de compteurs où chaque insertion incrémente plusieurs compteurs correspondants. La fréquence estimée est le minimum des compteurs atteints par les fonctions de hachage.

**Propriétés fondamentales :**
- ✅ **Jamais de sous-estimation** : La valeur estimée est toujours ≥ à la valeur réelle
- ✅ **Mémoire constante** : La mémoire utilisée ne dépend pas du nombre d'éléments
- ✅ **Temps constant** : Les opérations sont en O(depth)
- ⚠️ **Surestimation possible** : Les collisions de hachage peuvent gonfler les compteurs

## Installation

```bash
composer require andydefer/algo-kit
```

Prérequis :
- PHP 8.1 ou supérieur
- Extension `storage-kit` installée

## API / Méthodes publiques

### `__construct(StorageInterface $storage, int $width = 10000, int $depth = 5, string $key = 'cms')`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$storage` | `StorageInterface` | Backend de stockage pour la persistance |
| `$width` | `int` | Nombre de colonnes par ligne (plus grand = plus précis) |
| `$depth` | `int` | Nombre de fonctions de hachage / lignes (plus grand = plus précis) |
| `$key` | `string` | Clé unique identifiant le sketch (défaut : 'cms') |

**Retourne :** `void`

**Exemple :**
```php
$storage = new MemoryStorage();
$cms = new CountMinSketch($storage, 100000, 5, 'search_frequencies');
```

---

### `add(string $value, ?string $context = null): void`

Incrémente le compteur de fréquence pour une valeur donnée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `string` | La valeur à compter |
| `$context` | `string|null` | Contexte optionnel pour isoler les données |

**Retourne :** `void`

**Exemple :**
```php
$cms->add('laravel');
$cms->add('laravel');
$cms->add('php', 'search_engine');
// 'laravel' : 2 occurrences, 'php' : 1 occurrence dans 'search_engine'
```

---

### `count(string $value, ?string $context = null): int`

Estime la fréquence d'une valeur donnée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `string` | La valeur à compter |
| `$context` | `string|null` | Contexte optionnel pour isoler les données |

**Retourne :** `int` - Estimation de la fréquence (minimum des compteurs)

**Exemple :**
```php
$freq = $cms->count('laravel'); // Retourne ~2
$freqContext = $cms->count('php', 'search_engine'); // Retourne ~1
```

---

### `addBatch(CountMinSketchCollection $collection): void`

Ajoute plusieurs valeurs en lot pour de meilleures performances.

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

Estime les fréquences de plusieurs valeurs en lot.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$collection` | `CountMinSketchCollection` | Collection de valeurs à compter |

**Retourne :** `CountMinSketchResultCollection` - Collection des résultats avec leurs fréquences

**Exemple :**
```php
$collection = new CountMinSketchCollection();
$collection->add(new CountMinSketchRecord('php'));
$collection->add(new CountMinSketchRecord('laravel'));

$results = $cms->countBatch($collection);
foreach ($results as $result) {
    echo "{$result->value}: {$result->count}\n";
}
```

---

### `clear(?string $context = null): void`

Supprime toutes les données du sketch.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$context` | `string|null` | Si fourni, supprime seulement ce contexte |

**Retourne :** `void`

**Exemple :**
```php
// Supprimer un contexte spécifique
$cms->clear('search_engine');

// Supprimer tout le sketch
$cms->clear();
```

---

## Cas d'utilisation

### Cas 1 : Analyse de logs serveur

```php
<?php

declare(strict_types=1);

use AndyDefer\AlgoKIT\Algorithms\CountMinSketch;
use AndyDefer\StorageKit\Storage\MemoryStorage;

$storage = new MemoryStorage();
$logAnalyzer = new CountMinSketch($storage, 100000, 5, 'logs');

// Simuler des logs d'accès
$endpoints = ['/home', '/api/users', '/home', '/api/posts', '/home', '/api/users', '/api/users'];

foreach ($endpoints as $endpoint) {
    $logAnalyzer->add($endpoint);
}

echo "Fréquence des endpoints :\n";
echo "/home : " . $logAnalyzer->count('/home') . "\n";          // ~3
echo "/api/users : " . $logAnalyzer->count('/api/users') . "\n"; // ~3
echo "/api/posts : " . $logAnalyzer->count('/api/posts') . "\n"; // ~1
echo "/api/comments : " . $logAnalyzer->count('/api/comments') . "\n"; // ~0
```

### Cas 2 : Détection de requêtes fréquentes (rate limiting)

```php
<?php

declare(strict_types=1);

use AndyDefer\AlgoKIT\Algorithms\CountMinSketch;
use AndyDefer\StorageKit\Storage\MemoryStorage;

class RateLimiter
{
    private CountMinSketch $cms;
    private int $limitPerMinute;
    
    public function __construct(CountMinSketch $cms, int $limitPerMinute = 60)
    {
        $this->cms = $cms;
        $this->limitPerMinute = $limitPerMinute;
    }
    
    public function checkRequest(string $userId): bool
    {
        $minute = date('Y-m-d-H-i');
        $key = "{$userId}_{$minute}";
        
        $count = $this->cms->count($key);
        
        if ($count >= $this->limitPerMinute) {
            return false; // Limite atteinte
        }
        
        $this->cms->add($key);
        return true;
    }
}

// Utilisation
$storage = new MemoryStorage();
$cms = new CountMinSketch($storage, 10000, 3, 'rate_limit');
$limiter = new RateLimiter($cms, 3);

$userId = 'user_123';
for ($i = 0; $i < 5; $i++) {
    if ($limiter->checkRequest($userId)) {
        echo "✅ Requête acceptée ($i)\n";
    } else {
        echo "❌ Requête rejetée ($i) - Limite atteinte\n";
    }
}
// Sortie :
// ✅ Requête acceptée (0)
// ✅ Requête acceptée (1)
// ✅ Requête acceptée (2)
// ❌ Requête rejetée (3) - Limite atteinte
// ❌ Requête rejetée (4) - Limite atteinte
```

### Cas 3 : Suivi de mots-clés sur les réseaux sociaux

```php
<?php

declare(strict_types=1);

use AndyDefer\AlgoKIT\Algorithms\CountMinSketch;
use AndyDefer\StorageKit\Storage\MemoryStorage;

class TrendingKeywords
{
    private CountMinSketch $cms;
    private array $keywords = [];
    
    public function __construct(CountMinSketch $cms)
    {
        $this->cms = $cms;
    }
    
    public function track(string $keyword): void
    {
        $day = date('Y-m-d');
        $this->cms->add($keyword, $day);
        
        // Mettre à jour la liste des mots-clés suivis
        if (!in_array($keyword, $this->keywords)) {
            $this->keywords[] = $keyword;
        }
    }
    
    public function getTopKeywords(int $limit = 5): array
    {
        $scores = [];
        $day = date('Y-m-d');
        
        foreach ($this->keywords as $keyword) {
            $scores[$keyword] = $this->cms->count($keyword, $day);
        }
        
        arsort($scores);
        return array_slice($scores, 0, $limit, true);
    }
}

// Utilisation
$storage = new MemoryStorage();
$cms = new CountMinSketch($storage, 50000, 5, 'trending');
$trending = new TrendingKeywords($cms);

// Simuler des tweets
$tweets = ['#php', '#laravel', '#php', '#javascript', '#php', '#laravel', '#python', '#php'];

foreach ($tweets as $tweet) {
    $trending->track($tweet);
}

echo "📈 Tendances du jour :\n";
foreach ($trending->getTopKeywords(3) as $keyword => $count) {
    echo "  $keyword : $count mentions\n";
}
// Sortie :
// 📈 Tendances du jour :
//   #php : 4 mentions
//   #laravel : 2 mentions
//   #javascript : 1 mention
```

### Cas 4 : Analyse de popularité de produits

```php
<?php

declare(strict_types=1);

use AndyDefer\AlgoKIT\Algorithms\CountMinSketch;
use AndyDefer\StorageKit\Storage\MemoryStorage;

class ProductAnalytics
{
    private CountMinSketch $cms;
    
    public function __construct(CountMinSketch $cms)
    {
        $this->cms = $cms;
    }
    
    public function viewProduct(string $productId, string $category): void
    {
        // Vue globale
        $this->cms->add("product_{$productId}");
        
        // Vue par catégorie
        $this->cms->add("category_{$category}");
        
        // Vue par produit dans sa catégorie
        $this->cms->add("{$category}_product_{$productId}");
    }
    
    public function getProductViews(string $productId): int
    {
        return $this->cms->count("product_{$productId}");
    }
    
    public function getCategoryViews(string $category): int
    {
        return $this->cms->count("category_{$category}");
    }
    
    public function getProductViewsInCategory(string $productId, string $category): int
    {
        return $this->cms->count("{$category}_product_{$productId}");
    }
}

// Utilisation
$storage = new MemoryStorage();
$cms = new CountMinSketch($storage, 100000, 5, 'product_views');
$analytics = new ProductAnalytics($cms);

// Simuler des vues
$views = [
    ['p1', 'electronics'],
    ['p2', 'books'],
    ['p1', 'electronics'],
    ['p3', 'electronics'],
    ['p1', 'electronics'],
    ['p2', 'books'],
];

foreach ($views as [$product, $category]) {
    $analytics->viewProduct($product, $category);
}

echo "Vues produit p1 : " . $analytics->getProductViews('p1') . "\n"; // ~3
echo "Vues catégorie electronics : " . $analytics->getCategoryViews('electronics') . "\n"; // ~4
echo "Vues p1 dans electronics : " . $analytics->getProductViewsInCategory('p1', 'electronics') . "\n"; // ~3
```

## Flux d'exécution

### Insertion d'une valeur

```
add($value, $context)
    ↓
getTable($context) → Récupérer la matrice
    ↓
Pour i = 0 à depth - 1 :
    index = hashValue($value, i) → Calculer l'index
    incrementCounter(table, i, index) → Incrémenter le compteur
    ↓
saveTable($table, $context) → Persister
```

### Comptage d'une valeur

```
count($value, $context)
    ↓
getTable($context) → Récupérer la matrice
    ↓
minFrequency = PHP_INT_MAX
    ↓
Pour i = 0 à depth - 1 :
    index = hashValue($value, i)
    frequency = getCounterValue(table, i, index)
    minFrequency = min(minFrequency, frequency)
    ↓
Retourner minFrequency
```

### Opérations Batch (optimisées)

```
addBatch($collection)
    ↓
Pour chaque élément :
    1. Récupérer la table du contexte
    2. Incrémenter tous les compteurs
    3. Sauvegarder la table mise à jour
    ↓
countBatch($collection)
    ↓
Pour chaque élément :
    1. Récupérer la table du contexte (avec cache)
    2. Calculer le minimum des compteurs
    3. Ajouter le résultat à la collection
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
use AndyDefer\AlgoKIT\Algorithms\CountMinSketch;

// Stockage en mémoire (pour les tests)
$memoryStorage = new MemoryStorage();
$cms = new CountMinSketch($memoryStorage);

// Stockage persistant avec cache
$cacheStorage = new CacheStorage('redis');
$cms = new CountMinSketch($cacheStorage, 100000, 5, 'production_cms');
```

### Avec les collections

```php
use AndyDefer\AlgoKIT\Collections\CountMinSketchCollection;
use AndyDefer\AlgoKIT\Collections\CountMinSketchResultCollection;
use AndyDefer\AlgoKIT\Records\CountMinSketchRecord;
use AndyDefer\AlgoKIT\Records\CountMinSketchResultRecord;

// Créer une collection de valeurs
$collection = new CountMinSketchCollection();
$collection->add(new CountMinSketchRecord('value1'));
$collection->add(new CountMinSketchRecord('value2', 'context'));

// Comptage en batch
$results = $cms->countBatch($collection);

// Filtrer les résultats positifs
$positive = $results->filter(
    fn(CountMinSketchResultRecord $r) => $r->count > 0
);
```

### Avec les autres algorithmes

```php
use AndyDefer\AlgoKIT\Algorithms\TopK;
use AndyDefer\AlgoKIT\Algorithms\CountMinSketch;

// CountMinSketch pour les fréquences
$cms = new CountMinSketch($storage, 100000, 5, 'frequencies');

// TopK pour les éléments les plus fréquents
$topK = new TopK($storage, 10, 'top');

// Combinaison des deux
$items = ['php', 'laravel', 'php', 'python', 'laravel', 'php'];
foreach ($items as $item) {
    $cms->add($item);
    $topK->add($item);
}

// Obtenir les top éléments avec leurs fréquences estimées
foreach ($topK->getTop() as $item) {
    $estimated = $cms->count($item->value);
    echo "{$item->value}: réel={$item->count}, estimé={$estimated}\n";
}
```

## Performance

| Opération | Complexité | Description |
|-----------|------------|-------------|
| `add()` | O(depth) | depth incrémentations de compteurs |
| `count()` | O(depth) | depth lectures de compteurs |
| `addBatch()` | O(n × depth) | n = nombre d'éléments |
| `countBatch()` | O(n × depth) | Avec cache des contextes |
| `clear()` | O(1) | Suppression de la clé en storage |

**Précision :**
- L'erreur est bornée par `(width / 2) × depth` avec une probabilité de `1 - e^(-depth)`
- Plus `width` et `depth` sont grands, meilleure est la précision
- La mémoire utilisée est `width × depth` compteurs

**Recommandations :**

| Usage | Width | Depth | Erreur estimée |
|-------|-------|-------|----------------|
| Petit volume (< 10k) | 1 000 | 3 | ~2% |
| Volume moyen (< 1M) | 10 000 | 5 | ~0.5% |
| Grand volume (< 10M) | 100 000 | 7 | ~0.1% |
| Très grand volume | 1 000 000 | 9 | ~0.01% |

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

use AndyDefer\AlgoKIT\Algorithms\CountMinSketch;
use AndyDefer\AlgoKIT\Collections\CountMinSketchCollection;
use AndyDefer\AlgoKIT\Records\CountMinSketchRecord;
use AndyDefer\StorageKit\Storage\MemoryStorage;

// 1. Initialisation
$storage = new MemoryStorage();
$cms = new CountMinSketch($storage, 1000, 3, 'demo_cms');

echo "📊 DÉMONSTRATION COUNT-MIN SKETCH\n";
echo "═══════════════════════════════════\n\n";

// 2. Insertion de données
echo "📝 Insertion de données :\n";
$data = [
    ['php', 'langages'],
    ['laravel', 'frameworks'],
    ['php', 'langages'],
    ['php', 'langages'],
    ['python', 'langages'],
    ['laravel', 'frameworks'],
    ['javascript', 'langages'],
    ['php', 'langages'],
];

foreach ($data as [$value, $context]) {
    $cms->add($value, $context);
    echo "  + {$value} ({$context})\n";
}

// 3. Comptage individuel
echo "\n🔍 Comptage individuel :\n";
$tests = [
    ['php', 'langages'],
    ['python', 'langages'],
    ['laravel', 'frameworks'],
    ['ruby', 'langages'],
    ['php', 'frameworks'], // Contexte différent
];

foreach ($tests as [$value, $context]) {
    $count = $cms->count($value, $context);
    echo "  {$value} ({$context}) : {$count}\n";
}

// 4. Opérations batch
echo "\n📦 Opérations batch :\n";

// Insertion batch
$batch = new CountMinSketchCollection();
$batch->add(new CountMinSketchRecord('golang', 'langages'));
$batch->add(new CountMinSketchRecord('golang', 'langages'));
$batch->add(new CountMinSketchRecord('symfony', 'frameworks'));

$cms->addBatch($batch);
echo "  ✓ Insertion batch effectuée\n";

// Comptage batch
$query = new CountMinSketchCollection();
$query->add(new CountMinSketchRecord('php', 'langages'));
$query->add(new CountMinSketchRecord('golang', 'langages'));
$query->add(new CountMinSketchRecord('symfony', 'frameworks'));
$query->add(new CountMinSketchRecord('vuejs', 'frameworks'));

$results = $cms->countBatch($query);
foreach ($results as $result) {
    echo "  {$result->value} ({$result->context}) : {$result->count}\n";
}

// 5. Statistiques
echo "\n📈 Statistiques :\n";
$totalPhp = $cms->count('php', 'langages');
$totalLaravel = $cms->count('laravel', 'frameworks');
$totalGolang = $cms->count('golang', 'langages');

echo "  Total PHP : {$totalPhp}\n";
echo "  Total Laravel : {$totalLaravel}\n";
echo "  Total Golang : {$totalGolang}\n";

// 6. Nettoyage
echo "\n🧹 Nettoyage :\n";
$cms->clear('langages');
echo "  ✓ Contexte 'langages' vidé\n";

// Vérification après nettoyage
$phpAfter = $cms->count('php', 'langages');
$laravelAfter = $cms->count('laravel', 'frameworks');
echo "  PHP (langages) : {$phpAfter}\n";
echo "  Laravel (frameworks) : {$laravelAfter}\n";

// 7. Nettoyage complet
$cms->clear();
echo "  ✓ Nettoyage complet effectué\n";

// Exemple de sortie :
// 📊 DÉMONSTRATION COUNT-MIN SKETCH
// ═══════════════════════════════════
// 
// 📝 Insertion de données :
//   + php (langages)
//   + laravel (frameworks)
//   + php (langages)
//   + php (langages)
//   + python (langages)
//   + laravel (frameworks)
//   + javascript (langages)
//   + php (langages)
// 
// 🔍 Comptage individuel :
//   php (langages) : 5
//   python (langages) : 1
//   laravel (frameworks) : 2
//   ruby (langages) : 0
//   php (frameworks) : 0
// 
// 📦 Opérations batch :
//   ✓ Insertion batch effectuée
//   php (langages) : 5
//   golang (langages) : 2
//   symfony (frameworks) : 1
//   vuejs (frameworks) : 0
// 
// 📈 Statistiques :
//   Total PHP : 5
//   Total Laravel : 2
//   Total Golang : 2
// 
// 🧹 Nettoyage :
//   ✓ Contexte 'langages' vidé
//   PHP (langages) : 0
//   Laravel (frameworks) : 2
//   ✓ Nettoyage complet effectué
```

## Voir aussi

- [`top-k`](top-k.md) - Structure pour les éléments les plus fréquents
- [`bloom-filter`](bloom-filter.md) - Test probabiliste d'appartenance
- [`hyper-log-log`](hyper-log-log.md) - Estimation de cardinalité
- [`bk-tree`](bk-tree.md) - Recherche floue par distance de Levenshtein
- [`trie`](trie.md) - Recherche par préfixe