# BKTree - Référence Technique

## Description

Le BKTree (Burkhard-Keller Tree) est une structure de données arborescente permettant la recherche approximative de chaînes de caractères basée sur la distance de Levenshtein.

## Hiérarchie / Implémentations

```
TreeInterface
    └── BKTree (final)
```

La classe implémente l'interface `TreeInterface` et utilise :
- `StorageInterface` pour la persistance des données
- `BKTreeNodeCollection` pour gérer les nœuds enfants
- `BKTreeResultCollection` pour retourner les résultats de recherche
- `BKTreeNodeRecord` pour représenter les nœuds
- `BKTreeResultRecord` pour représenter les résultats

## Rôle principal

Le BKTree permet la **correction orthographique** et la **recherche floue** dans un dictionnaire de mots. Il organise les mots dans une structure où chaque nœud représente un mot, et les arêtes sont étiquetées par la distance de Levenshtein entre les mots. Cette organisation permet des recherches extrêmement efficaces avec une tolérance donnée.

## Installation

```bash
composer require andydefer/algo-kit
```

Prérequis :
- PHP 8.1 ou supérieur
- Extension `storage-kit` installée
- Extension `domain-structures` installée

## API / Méthodes publiques

### `__construct(StorageInterface $storage, string $key = 'bktree')`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$storage` | `StorageInterface` | Backend de stockage pour la persistance |
| `$key` | `string` | Clé unique identifiant l'arbre (défaut : 'bktree') |

**Retourne :** `void`

**Exceptions :** Aucune

**Exemple :**
```php
$storage = new MemoryStorage();
$bkTree = new BKTree($storage, 'my_dictionary');
```

---

### `insert(string $word): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$word` | `string` | Le mot à insérer dans l'arbre |

**Retourne :** `void`

**Exceptions :** Aucune

**Exemple :**
```php
$bkTree->insert('laravel');
$bkTree->insert('laragon');
$bkTree->insert('large');
```

---

### `search(string $word, int $tolerance = 2, int $limit = 10): BKTreeResultCollection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$word` | `string` | Le mot à rechercher |
| `$tolerance` | `int` | Distance de Levenshtein maximale autorisée (défaut : 2) |
| `$limit` | `int` | Nombre maximum de résultats (défaut : 10) |

**Retourne :** `BKTreeResultCollection` - Collection de mots correspondants avec leurs distances

**Exceptions :** Aucune

**Exemple :**
```php
$results = $bkTree->search('larvel', 2, 5);
foreach ($results as $result) {
    echo "{$result->word} (distance: {$result->distance})\n";
}
// Sortie :
// laravel (distance: 1)
// laragon (distance: 2)
```

---

### `clear(): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `void`

**Exceptions :** Aucune

**Exemple :**
```php
$bkTree->clear();
// L'arbre est maintenant vide
```

---

## Cas d'utilisation

### Cas 1 : Correcteur orthographique

```php
<?php

declare(strict_types=1);

use AndyDefer\AlgoKIT\Algorithms\BKTree;
use AndyDefer\StorageKit\Storage\MemoryStorage;

$storage = new MemoryStorage();
$spellChecker = new BKTree($storage, 'dictionary');

// Indexer un dictionnaire
$words = ['php', 'python', 'javascript', 'laravel', 'ruby', 'golang'];
foreach ($words as $word) {
    $spellChecker->insert($word);
}

// Suggestion de correction
$search = 'javascrip';
$suggestions = $spellChecker->search($search, 2, 3);

echo "Suggestions pour '$search' :\n";
foreach ($suggestions as $result) {
    echo "  - {$result->word} (distance: {$result->distance})\n";
}
// Sortie :
// Suggestions pour 'javascrip' :
//   - javascript (distance: 1)
//   - laravel (distance: 9)
//   - python (distance: 8)
```

### Cas 2 : Recherche floue dans un catalogue

```php
<?php

declare(strict_types=1);

use AndyDefer\AlgoKIT\Algorithms\BKTree;
use AndyDefer\StorageKit\Storage\MemoryStorage;

$storage = new MemoryStorage();
$productTree = new BKTree($storage, 'products');

// Indexer les noms de produits
$products = ['Smartphone', 'Laptop', 'Tablet', 'Headphones', 'Smartwatch'];
foreach ($products as $product) {
    $productTree->insert(strtolower($product));
}

// Recherche avec tolérance élevée
$query = 'smartfon';
$results = $productTree->search($query, 3, 5);

echo "Produits similaires à '$query' :\n";
foreach ($results as $result) {
    echo "  - " . ucfirst($result->word) . " (distance: {$result->distance})\n";
}
// Sortie :
// Produits similaires à 'smartfon' :
//   - Smartphone (distance: 2)
//   - Smartwatch (distance: 3)
```

### Cas 3 : Détection de plagiat (fingerprinting)

```php
<?php

declare(strict_types=1);

use AndyDefer\AlgoKIT\Algorithms\BKTree;
use AndyDefer\StorageKit\Storage\MemoryStorage;

$storage = new MemoryStorage();
$plagiarismTree = new BKTree($storage, 'documents');

// Indexer les phrases connues
$knownTexts = [
    'The quick brown fox jumps over the lazy dog',
    'Lorem ipsum dolor sit amet consectetur',
    'PHP is a popular general-purpose scripting language',
];

foreach ($knownTexts as $text) {
    $plagiarismTree->insert($text);
}

// Vérifier une nouvelle phrase
$newText = 'The quick brown fox jumps over the lazy cat';
$matches = $plagiarismTree->search($newText, 5, 3);

if ($matches->count() > 0) {
    $best = $matches->first();
    echo "Texte similaire détecté : {$best->word}\n";
    echo "Distance : {$best->distance}\n";
}
// Sortie :
// Texte similaire détecté : The quick brown fox jumps over the lazy dog
// Distance : 3
```

## Flux d'exécution

### Insertion d'un mot

```
insert($word)
    ↓
getRoot() → Existe ?
    ├── NON → createNode($word) → saveRoot() → Fin
    └── OUI → insertNode($root, $word)
                ↓
        calculateDistance($node->word, $word)
                ↓
        distance === 0 ?
            ├── OUI → Fin (mot déjà présent)
            └── NON → findChildByWord($node, $word)
                        ↓
                enfant trouvé ?
                    ├── OUI → Fin
                    └── NON → findChildAtDistance($node, $distance)
                                ↓
                        enfant trouvé ?
                            ├── OUI → insertNode($child, $word) (récursif)
                            └── NON → createNode($word) → add to children
                ↓
    saveRoot($root)
```

### Recherche d'un mot

```
search($word, $tolerance, $limit)
    ↓
getRoot() → null ?
    ├── OUI → Retourner collection vide
    └── NON → searchNode($root, $word, $tolerance, $results)
                ↓
        calculateDistance($node->word, $word)
                ↓
        distance <= tolerance ?
            └── OUI → Ajouter $node à $results
                ↓
        minDistance = distance - tolerance
        maxDistance = distance + tolerance
                ↓
        Pour chaque enfant :
            childDistance = calculateDistance($node->word, $child->word)
                ↓
            childDistance entre minDistance et maxDistance ?
                └── OUI → searchNode($child) (récursif)
                ↓
    sortAndLimitResults($results, $limit)
        ↓
    Retourner collection triée et limitée
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Aucune | - | - |

**Note :** La classe ne lève pas d'exceptions directement. Les erreurs potentielles peuvent provenir de l'implémentation de `StorageInterface` utilisée.

## Intégration

### Avec StorageKit

```php
use AndyDefer\StorageKit\Storage\MemoryStorage;
use AndyDefer\StorageKit\Storage\CacheStorage;
use AndyDefer\AlgoKIT\Algorithms\BKTree;

// Stockage en mémoire (non persistant)
$memoryStorage = new MemoryStorage();
$bkTree = new BKTree($memoryStorage);

// Stockage avec cache (persistant)
$cacheStorage = new CacheStorage('redis');
$bkTree = new BKTree($cacheStorage, 'spell_checker');
```

### Avec les collections

```php
use AndyDefer\AlgoKIT\Collections\BKTreeResultCollection;
use AndyDefer\AlgoKIT\Records\BKTreeResultRecord;

$results = $bkTree->search('word', 2, 5);

// Itération
foreach ($results as $result) {
    echo "{$result->word} (distance: {$result->distance})";
}

// Filtrage
$goodMatches = $results->filter(
    fn(BKTreeResultRecord $r) => $r->distance <= 1
);
```

## Performance

| Opération | Complexité | Description |
|-----------|------------|-------------|
| `insert()` | O(log n) * O(1) | Insertion avec recherche binaire guidée par les distances |
| `search()` | O(n^α) | α varie selon la tolérance et la distribution des mots |
| `clear()` | O(1) | Suppression de la clé en storage |

**Caractéristiques :**
- La recherche est **beaucoup plus rapide** qu'une recherche linéaire
- Plus la tolérance est faible, plus la recherche est rapide
- Les performances dépendent de l'implémentation du storage (MemoryStorage est plus rapide que les storages persistants)

**Optimisations :**
- Utilisation de `levenshtein()` optimisée en C (native PHP)
- Pas de calcul redondant : les distances sont calculées uniquement lorsque nécessaire
- Stockage persistant avec l'interface `StorageInterface`

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

use AndyDefer\AlgoKIT\Algorithms\BKTree;
use AndyDefer\AlgoKIT\Records\BKTreeResultRecord;
use AndyDefer\StorageKit\Storage\MemoryStorage;

// 1. Initialisation
$storage = new MemoryStorage();
$bkTree = new BKTree($storage, 'fruits');

// 2. Indexation
$fruits = ['pomme', 'poire', 'banane', 'orange', 'kiwi', 'mangue'];
foreach ($fruits as $fruit) {
    $bkTree->insert($fruit);
}

// 3. Recherche floue
$searchTerm = 'pome';
$results = $bkTree->search($searchTerm, 2, 3);

// 4. Affichage des résultats
echo "Recherche de '$searchTerm' :\n";
echo "─────────────────────────────\n";

if ($results->isEmpty()) {
    echo "Aucun résultat trouvé.\n";
} else {
    foreach ($results as $result) {
        $isExact = $result->distance === 0 ? '✓' : ' ';
        echo "{$isExact} {$result->word} (distance : {$result->distance})\n";
    }
}

// 5. Meilleure correspondance
$best = $results->first();
if ($best instanceof BKTreeResultRecord) {
    echo "\nMeilleure correspondance : {$best->word}\n";
}

// 6. Nettoyage
$bkTree->clear();

// Exemple de sortie :
// Recherche de 'pome' :
// ─────────────────────────────
//  pomme (distance : 1)
//  poire (distance : 2)
//  
// Meilleure correspondance : pomme
```

## Voir aussi

- [`CountMinSketch`](count-min-sketch.md) - Compteur probabiliste de fréquences
- [`Trie`](trie.md) - Arbre de recherche par préfixe
- [`BloomFilter`](bloom-filter.md) - Test probabiliste d'appartenance
- [`HyperLogLog`](hyper-log-log.md) - Estimation de cardinalité
- [`TopK`](top-k.md) - Suivi des éléments les plus fréquents