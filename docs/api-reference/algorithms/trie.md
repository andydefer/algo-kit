# Trie - Référence Technique

## Description

Le Trie (arbre préfixe) est une structure de données arborescente permettant une recherche efficace par préfixe. Chaque nœud représente un caractère, et les mots partageant un préfixe commun partagent le même chemin, ce qui rend les recherches de préfixes extrêmement rapides.

## Hiérarchie / Implémentations

```
TrieInterface
    └── Trie (final)
```

La classe implémente l'interface `TrieInterface` et utilise :
- `StorageInterface` pour la persistance des données
- `TrieCollection` pour les opérations batch
- `TrieResultCollection` pour retourner les résultats
- `TrieRecord` pour représenter un mot à insérer
- `TrieResultRecord` pour représenter un résultat de recherche

## Rôle principal

Le Trie répond à la question **"Quels mots commencent par ce préfixe ?"** en temps O(L) où L est la longueur du préfixe. Particulièrement adapté pour les fonctionnalités d'autocomplétion, de recherche par préfixe et de suggestions de mots.

**Propriétés fondamentales :**
- ✅ **Recherche rapide** : O(L) où L est la longueur du préfixe
- ✅ **Partage de mémoire** : Les préfixes communs ne sont stockés qu'une fois
- ✅ **Persistance** : Sauvegarde automatique dans le storage
- ✅ **Contexte** : Isolation des données par contexte
- ⚠️ **Mémoire** : Peut être plus gourmand qu'une simple liste pour des mots courts

## Installation

```bash
composer require andydefer/algo-kit
```

Prérequis :
- PHP 8.1 ou supérieur
- Extension `storage-kit` installée

## API / Méthodes publiques

### `__construct(StorageInterface $storage, string $key = 'trie')`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$storage` | `StorageInterface` | Backend de stockage pour la persistance |
| `$key` | `string` | Clé unique identifiant le trie (défaut : 'trie') |

**Retourne :** `void`

**Exemple :**
```php
$storage = new MemoryStorage();
$trie = new Trie($storage, 'city_names');
```

---

### `insert(string $word, ?string $context = null): void`

Insère un mot dans le trie.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$word` | `string` | Le mot à insérer |
| `$context` | `string|null` | Contexte optionnel pour isoler les données |

**Retourne :** `void`

**Exemple :**
```php
$trie->insert('laravel');
$trie->insert('laragon');
$trie->insert('laptop', 'products');
```

---

### `search(string $prefix, ?string $context = null, int $limit = 10): TrieResultCollection`

Recherche les mots commençant par un préfixe donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$prefix` | `string` | Le préfixe à rechercher |
| `$context` | `string|null` | Contexte optionnel pour isoler les données |
| `$limit` | `int` | Nombre maximum de résultats (défaut : 10) |

**Retourne :** `TrieResultCollection` - Collection des mots correspondants

**Exemple :**
```php
$results = $trie->search('lar', null, 5);
foreach ($results as $result) {
    echo $result->word . "\n";
}
// laravel
// laragon
// large
```

---

### `insertBatch(TrieCollection $collection): void`

Insère plusieurs mots en lot.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$collection` | `TrieCollection` | Collection de mots à insérer |

**Retourne :** `void`

**Exemple :**
```php
$collection = new TrieCollection();
$collection->add(new TrieRecord('php'));
$collection->add(new TrieRecord('python'));
$collection->add(new TrieRecord('javascript'));
$trie->insertBatch($collection);
```

---

### `searchBatch(TrieCollection $collection, int $limit = 10): array`

Recherche plusieurs préfixes en lot.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$collection` | `TrieCollection` | Collection de préfixes à rechercher |
| `$limit` | `int` | Maximum de résultats par préfixe (défaut : 10) |

**Retourne :** `array<string, TrieResultCollection>` - Map préfixe → résultats

**Exemple :**
```php
$collection = new TrieCollection();
$collection->add(new TrieRecord('la'));
$collection->add(new TrieRecord('py'));

$results = $trie->searchBatch($collection);
foreach ($results['la'] as $result) {
    echo $result->word . "\n";
}
```

---

### `clear(): void`

Supprime toutes les données du trie.

**Retourne :** `void`

**Exemple :**
```php
$trie->clear(); // Réinitialise complètement
```

---

## Cas d'utilisation

### Cas 1 : Autocomplétion de recherche

```php
<?php

declare(strict_types=1);

use AndyDefer\AlgoKIT\Algorithms\Trie;
use AndyDefer\StorageKit\Storage\MemoryStorage;

class SearchAutocomplete
{
    private Trie $trie;
    
    public function __construct(Trie $trie)
    {
        $this->trie = $trie;
    }
    
    public function indexTerms(array $terms): void
    {
        foreach ($terms as $term) {
            $this->trie->insert($term);
        }
    }
    
    public function suggest(string $query, int $limit = 5): array
    {
        $results = $this->trie->search($query, null, $limit);
        return array_map(
            fn($result) => $result->word,
            $results->toArray()
        );
    }
}

// Utilisation
$storage = new MemoryStorage();
$trie = new Trie($storage, 'search_terms');
$autocomplete = new SearchAutocomplete($trie);

// Indexer les termes de recherche populaires
$terms = [
    'php', 'python', 'javascript', 'laravel', 'laragon',
    'pandas', 'pytorch', 'tensorflow', 'docker', 'kubernetes'
];

$autocomplete->indexTerms($terms);

// Suggestions
$queries = ['py', 'la', 'do'];
foreach ($queries as $query) {
    $suggestions = $autocomplete->suggest($query, 3);
    echo "🔍 '$query' → " . implode(', ', $suggestions) . "\n";
}
// Sortie :
// 🔍 'py' → python, pytorch, pandas
// 🔍 'la' → laravel, laragon
// 🔍 'do' → docker
```

### Cas 2 : Catalogue de produits par contexte

```php
<?php

declare(strict_types=1);

use AndyDefer\AlgoKIT\Algorithms\Trie;
use AndyDefer\AlgoKIT\Collections\TrieCollection;
use AndyDefer\AlgoKIT\Records\TrieRecord;
use AndyDefer\StorageKit\Storage\MemoryStorage;

class ProductCatalog
{
    private Trie $trie;
    
    public function __construct(Trie $trie)
    {
        $this->trie = $trie;
    }
    
    public function addProduct(string $category, string $productName): void
    {
        $this->trie->insert($productName, $category);
    }
    
    public function addProductsBatch(string $category, array $products): void
    {
        $collection = new TrieCollection();
        foreach ($products as $product) {
            $collection->add(new TrieRecord($product, $category));
        }
        $this->trie->insertBatch($collection);
    }
    
    public function searchInCategory(string $category, string $prefix, int $limit = 10): array
    {
        $results = $this->trie->search($prefix, $category, $limit);
        return array_map(
            fn($result) => $result->word,
            $results->toArray()
        );
    }
}

// Utilisation
$storage = new MemoryStorage();
$trie = new Trie($storage, 'products');
$catalog = new ProductCatalog($trie);

// Ajouter des produits par catégorie
$catalog->addProductsBatch('electronics', [
    'smartphone', 'smartwatch', 'smarttv', 'speaker', 'headphones'
]);

$catalog->addProductsBatch('books', [
    'smart thinking', 'smart habits', 'php book', 'python guide'
]);

// Recherche dans chaque catégorie
$search = 'smart';
echo "📱 Électronique :\n";
foreach ($catalog->searchInCategory('electronics', $search, 3) as $product) {
    echo "  • $product\n";
}

echo "\n📚 Livres :\n";
foreach ($catalog->searchInCategory('books', $search, 3) as $product) {
    echo "  • $product\n";
}
// Sortie :
// 📱 Électronique :
//   • smartphone
//   • smartwatch
//   • smarttv
// 
// 📚 Livres :
//   • smart thinking
//   • smart habits
```

### Cas 3 : Dictionnaire multilingue

```php
<?php

declare(strict_types=1);

use AndyDefer\AlgoKIT\Algorithms\Trie;
use AndyDefer\StorageKit\Storage\MemoryStorage;

class Dictionary
{
    private Trie $trie;
    
    public function __construct(Trie $trie)
    {
        $this->trie = $trie;
    }
    
    public function addWord(string $word, string $language): void
    {
        $this->trie->insert($word, $language);
    }
    
    public function getSuggestions(string $prefix, string $language, int $limit = 5): array
    {
        $results = $this->trie->search($prefix, $language, $limit);
        return array_map(
            fn($result) => $result->word,
            $results->toArray()
        );
    }
    
    public function searchMultipleLanguages(string $prefix, array $languages, int $limit = 3): array
    {
        $collection = new TrieCollection();
        foreach ($languages as $lang) {
            $collection->add(new TrieRecord($prefix, $lang));
        }
        
        $results = $this->trie->searchBatch($collection, $limit);
        $suggestions = [];
        
        foreach ($languages as $lang) {
            $key = $lang . ':' . $prefix;
            if (isset($results[$key])) {
                $suggestions[$lang] = array_map(
                    fn($result) => $result->word,
                    $results[$key]->toArray()
                );
            }
        }
        
        return $suggestions;
    }
}

// Utilisation
$storage = new MemoryStorage();
$trie = new Trie($storage, 'dictionary');
$dictionary = new Dictionary($trie);

// Ajouter des mots dans différentes langues
$words = [
    'bonjour' => 'fr',
    'bonsoir' => 'fr',
    'bonne' => 'fr',
    'hello' => 'en',
    'help' => 'en',
    'hell' => 'en',
    'hola' => 'es',
    'hombre' => 'es',
];

foreach ($words as $word => $language) {
    $dictionary->addWord($word, $language);
}

// Suggestions par langue
echo "🇫🇷 Français :\n";
foreach ($dictionary->getSuggestions('bon', 'fr', 3) as $word) {
    echo "  • $word\n";
}

echo "\n🇬🇧 Anglais :\n";
foreach ($dictionary->getSuggestions('hel', 'en', 3) as $word) {
    echo "  • $word\n";
}

// Recherche multi-langues
echo "\n🌍 Recherche multi-langues :\n";
$results = $dictionary->searchMultipleLanguages('ho', ['fr', 'en', 'es'], 2);
foreach ($results as $lang => $words) {
    echo "  $lang : " . implode(', ', $words) . "\n";
}
// Sortie :
// 🇫🇷 Français :
//   • bonjour
//   • bonsoir
//   • bonne
// 
// 🇬🇧 Anglais :
//   • hello
//   • help
//   • hell
// 
// 🌍 Recherche multi-langues :
//   fr : 
//   en : 
//   es : hola, hombre
```

### Cas 4 : API d'autocomplétion avec batch

```php
<?php

declare(strict_types=1);

use AndyDefer\AlgoKIT\Algorithms\Trie;
use AndyDefer\AlgoKIT\Collections\TrieCollection;
use AndyDefer\AlgoKIT\Records\TrieRecord;
use AndyDefer\StorageKit\Storage\MemoryStorage;

class AutocompleteAPI
{
    private Trie $trie;
    
    public function __construct(Trie $trie)
    {
        $this->trie = $trie;
    }
    
    public function index($data): void
    {
        $collection = new TrieCollection();
        foreach ($data as $item) {
            $context = $item['category'] ?? null;
            $collection->add(new TrieRecord($item['name'], $context));
        }
        $this->trie->insertBatch($collection);
    }
    
    public function searchMultiple(array $queries, array $contexts = [], int $limit = 5): array
    {
        $collection = new TrieCollection();
        
        foreach ($queries as $query) {
            foreach ($contexts as $context) {
                $collection->add(new TrieRecord($query, $context));
            }
            $collection->add(new TrieRecord($query));
        }
        
        $results = $this->trie->searchBatch($collection, $limit);
        $formatted = [];
        
        foreach ($queries as $query) {
            $formatted[$query] = [];
            foreach ($contexts as $context) {
                $key = $context . ':' . $query;
                if (isset($results[$key])) {
                    $formatted[$query][$context] = array_map(
                        fn($r) => $r->word,
                        $results[$key]->toArray()
                    );
                }
            }
            // Résultats globaux
            $globalKey = $query;
            if (isset($results[$globalKey])) {
                $formatted[$query]['global'] = array_map(
                    fn($r) => $r->word,
                    $results[$globalKey]->toArray()
                );
            }
        }
        
        return $formatted;
    }
}

// Utilisation
$storage = new MemoryStorage();
$trie = new Trie($storage, 'api_autocomplete');
$api = new AutocompleteAPI($trie);

// Indexer des données
$data = [
    ['name' => 'php', 'category' => 'lang'],
    ['name' => 'python', 'category' => 'lang'],
    ['name' => 'phpstorm', 'category' => 'ide'],
    ['name' => 'phper', 'category' => 'lang'],
    ['name' => 'pandas', 'category' => 'lib'],
    ['name' => 'pytorch', 'category' => 'lib'],
];

$api->index($data);

// Recherche multiple
$queries = ['ph', 'py', 'pa'];
$contexts = ['lang', 'lib', 'ide'];

$results = $api->searchMultiple($queries, $contexts, 3);

foreach ($results as $query => $contextResults) {
    echo "🔍 '$query' :\n";
    foreach ($contextResults as $context => $words) {
        echo "  $context: " . implode(', ', $words) . "\n";
    }
    echo "\n";
}
// Sortie :
// 🔍 'ph' :
//   lang: php, phper
//   lib: 
//   ide: phpstorm
//   global: php, phper, phpstorm
// 
// 🔍 'py' :
//   lang: python
//   lib: pytorch
//   ide: 
//   global: python, pytorch
// 
// 🔍 'pa' :
//   lang: 
//   lib: pandas
//   ide: 
//   global: pandas
```

## Flux d'exécution

### Insertion d'un mot

```
insert($word, $context)
    ↓
getRoot() → Récupérer la racine
    ↓
currentNode = getContextNode($root, $context)
    ↓
Pour chaque caractère dans str_split($word) :
    currentNode = ensureChildNodeExists($currentNode, $character)
    ↓
addWordToNode($currentNode, $word)
    ↓
saveRoot($root) → Persister
```

### Recherche par préfixe

```
search($prefix, $context, $limit)
    ↓
getRoot() → Récupérer la racine
    ↓
startingNode = findNode($root, $prefix, $context)
    ↓
startingNode === null ?
    ├── OUI → Retourner collection vide
    └── NON → collectWords($startingNode, $prefix, $limit)
                ↓
        collectWordsRecursive() → Parcours DFS
                ↓
        Ajouter chaque mot trouvé à la collection
                ↓
Retourner collection
```

### Parcours récursif de collecte

```
collectWordsRecursive($node, $prefix, $limit, &$results)
    ↓
Pour chaque mot dans $node['words'] :
    Ajouter $word à $results
    Si count($results) >= $limit → Arrêter
    ↓
Pour chaque enfant ($character, $childNode) :
    Si count($results) >= $limit → Sortir de la boucle
    ↓
    collectWordsRecursive($childNode, $prefix . $character, $limit, $results)
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
use AndyDefer\AlgoKIT\Algorithms\Trie;

// Stockage en mémoire (pour les tests)
$memoryStorage = new MemoryStorage();
$trie = new Trie($memoryStorage);

// Stockage persistant avec cache
$cacheStorage = new CacheStorage('redis');
$trie = new Trie($cacheStorage, 'production_trie');
```

### Avec les collections

```php
use AndyDefer\AlgoKIT\Collections\TrieCollection;
use AndyDefer\AlgoKIT\Collections\TrieResultCollection;
use AndyDefer\AlgoKIT\Records\TrieRecord;
use AndyDefer\AlgoKIT\Records\TrieResultRecord;

// Créer une collection de mots à insérer
$collection = new TrieCollection();
$collection->add(new TrieRecord('laravel'));
$collection->add(new TrieRecord('python', 'lang'));

// Insertion batch
$trie->insertBatch($collection);

// Recherche batch
$search = new TrieCollection();
$search->add(new TrieRecord('la'));
$search->add(new TrieRecord('py', 'lang'));

$results = $trie->searchBatch($search);
foreach ($results as $key => $resultCollection) {
    $words = array_map(fn($r) => $r->word, $resultCollection->toArray());
    echo "$key: " . implode(', ', $words) . "\n";
}
```

### Avec les autres algorithmes

Le Trie peut être combiné avec d'autres algorithmes :

- **Avec BKTree** : Suggestions + corrections orthographiques
- **Avec CountMinSketch** : Suivi des fréquences des recherches

```php
use AndyDefer\AlgoKIT\Algorithms\Trie;
use AndyDefer\AlgoKIT\Algorithms\BKTree;

$trie = new Trie($storage, 'autocomplete');
$bkTree = new BKTree($storage, 'spell_check');

// Autocomplétion + correction
function suggestWithCorrection($query) {
    $suggestions = $trie->search($query);
    
    if ($suggestions->isEmpty()) {
        // Correction orthographique
        $corrected = $bkTree->search($query, 2, 1);
        if (!$corrected->isEmpty()) {
            $suggestions = $trie->search($corrected->first()->word);
        }
    }
    
    return $suggestions;
}
```

## Performance

| Opération | Complexité | Description |
|-----------|------------|-------------|
| `insert()` | O(L) | L = longueur du mot |
| `search()` | O(L + M) | L = longueur du préfixe, M = mots trouvés |
| `insertBatch()` | O(N × L) | N = nombre de mots |
| `searchBatch()` | O(P × (L + M)) | P = nombre de préfixes |

**Caractéristiques :**
- **Recherche rapide** : Indépendante du nombre total de mots
- **Mémoire partagée** : Les préfixes communs sont stockés une seule fois
- **Limite** : Contrôle le nombre de résultats retournés

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

use AndyDefer\AlgoKIT\Algorithms\Trie;
use AndyDefer\AlgoKIT\Collections\TrieCollection;
use AndyDefer\AlgoKIT\Records\TrieRecord;
use AndyDefer\StorageKit\Storage\MemoryStorage;

// 1. Initialisation
$storage = new MemoryStorage();
$trie = new Trie($storage, 'demo_trie');

echo "🌳 DÉMONSTRATION TRIE\n";
echo "═══════════════════════\n\n";

// 2. Insertion de mots
echo "📝 Insertion de mots :\n";
$words = ['php', 'python', 'pandas', 'laravel', 'laragon', 'laptop'];

foreach ($words as $word) {
    $trie->insert($word);
    echo "  + $word\n";
}

// 3. Recherche par préfixe
echo "\n🔍 Recherche par préfixe :\n";
$prefixes = ['la', 'py', 'p'];

foreach ($prefixes as $prefix) {
    $results = $trie->search($prefix, null, 5);
    $words = array_map(fn($r) => $r->word, $results->toArray());
    echo "  '$prefix' → " . implode(', ', $words) . "\n";
}

// 4. Insertion avec contexte
echo "\n📚 Insertion avec contexte :\n";
$trie->insert('php', 'language');
$trie->insert('phpstorm', 'ide');
$trie->insert('phpunit', 'tool');
$trie->insert('javascript', 'language');
echo "  + php (language)\n";
echo "  + phpstorm (ide)\n";
echo "  + phpunit (tool)\n";
echo "  + javascript (language)\n";

// 5. Recherche par contexte
echo "\n🔎 Recherche par contexte :\n";
$contexts = ['language', 'ide', 'tool'];

foreach ($contexts as $context) {
    $results = $trie->search('php', $context, 5);
    $words = array_map(fn($r) => $r->word, $results->toArray());
    $count = count($words);
    echo "  $context ($count) : " . implode(', ', $words) . "\n";
}

// 6. Opérations batch
echo "\n📦 Opérations batch :\n";

// Insertion batch
$batch = new TrieCollection();
$batch->add(new TrieRecord('golang', 'language'));
$batch->add(new TrieRecord('docker', 'tool'));
$batch->add(new TrieRecord('kubernetes', 'tool'));

$trie->insertBatch($batch);
echo "  ✓ Insertion batch effectuée\n";

// Recherche batch
$search = new TrieCollection();
$search->add(new TrieRecord('gol', 'language'));
$search->add(new TrieRecord('doc', 'tool'));
$search->add(new TrieRecord('kub', 'tool'));

$results = $trie->searchBatch($search, 3);
foreach ($results as $key => $resultCollection) {
    $words = array_map(fn($r) => $r->word, $resultCollection->toArray());
    echo "  $key → " . implode(', ', $words) . "\n";
}

// 7. Limite des résultats
echo "\n⏱️ Limite des résultats :\n";
$results = $trie->search('p', null, 2);
$words = array_map(fn($r) => $r->word, $results->toArray());
echo "  'p' (limit=2) → " . implode(', ', $words) . "\n";

// 8. Nettoyage
echo "\n🧹 Nettoyage...\n";
$trie->clear();
echo "  ✓ Trie vidé\n";

$empty = $trie->search('php');
echo "  Recherche après nettoyage : " . count($empty) . " résultats\n";

// Exemple de sortie :
// 🌳 DÉMONSTRATION TRIE
// ═══════════════════════
// 
// 📝 Insertion de mots :
//   + php
//   + python
//   + pandas
//   + laravel
//   + laragon
//   + laptop
// 
// 🔍 Recherche par préfixe :
//   'la' → laravel, laragon, laptop
//   'py' → python
//   'p' → php, python, pandas
// 
// 📚 Insertion avec contexte :
//   + php (language)
//   + phpstorm (ide)
//   + phpunit (tool)
//   + javascript (language)
// 
// 🔎 Recherche par contexte :
//   language (1) : php
//   ide (1) : phpstorm
//   tool (1) : phpunit
// 
// 📦 Opérations batch :
//   ✓ Insertion batch effectuée
//   language:gol → golang
//   tool:doc → docker
//   tool:kub → kubernetes
// 
// ⏱️ Limite des résultats :
//   'p' (limit=2) → php, python
// 
// 🧹 Nettoyage...
//   ✓ Trie vidé
//   Recherche après nettoyage : 0 résultats
```

## Voir aussi

- [`bk-tree`](bk-tree.md) - Recherche floue par distance de Levenshtein
- [`bloom-filter`](bloom-filter.md) - Test probabiliste d'appartenance
- [`count-min-sketch`](count-min-sketch.md) - Compteur probabiliste de fréquences
- [`hyper-log-log`](hyper-log-log.md) - Estimation de cardinalité
- [`top-k`](top-k.md) - Suivi des éléments les plus fréquents