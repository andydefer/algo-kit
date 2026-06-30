# BKTree - Référence Technique

## Description

BKTree (Burkhard-Keller Tree) est une structure de données pour la recherche approximative de mots basée sur la distance de Levenshtein. Elle permet de trouver les mots les plus proches d'un mot donné avec une tolérance configurable.

## Hiérarchie / Implémentations

```
TreeInterface
    └── BKTree
```

**Interfaces implémentées :** `TreeInterface`

## Rôle principal

BKTree organise les mots dans un arbre où chaque nœud est un mot, et les enfants sont organisés par distance de Levenshtein. Cette structure permet une recherche efficace des mots similaires avec une tolérance de distance donnée, idéale pour les systèmes de correction orthographique et de suggestion.

## Installation

```bash
composer require andydefer/algokit
```

## API / Méthodes publiques

### `__construct(StorageInterface $storage, string $key = 'bktree')`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$storage` | `StorageInterface` | Instance du système de stockage |
| `$key` | `string` | Clé d'identification dans le storage (défaut: 'bktree') |

**Retourne :** `void`

**Exemple :**
```php
$storage = new MemoryStorage();
$bkTree = new BKTree($storage, 'my_bktree');
```

---

### `insert(string $word): void`

Insère un mot dans l'arbre.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$word` | `string` | Mot à insérer |

**Retourne :** `void`

**Exemple :**
```php
$bkTree->insert('laravel');
$bkTree->insert('python');
$bkTree->insert('php');
```

---

### `search(string $word, int $tolerance = 2, int $limit = 10): BKTreeResultCollection`

Recherche les mots similaires à un mot donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$word` | `string` | Mot à rechercher |
| `$tolerance` | `int` | Distance maximale autorisée (Levenshtein) |
| `$limit` | `int` | Nombre maximum de résultats (défaut: 10) |

**Retourne :** `BKTreeResultCollection` - Collection de résultats triés par distance croissante

**Exemple :**
```php
$results = $bkTree->search('larvel', 2, 5);
// Retourne les 5 mots les plus proches de 'larvel' avec une distance ≤ 2
```

---

### `clear(): void`

Vide complètement l'arbre.

**Retourne :** `void`

**Exemple :**
```php
$bkTree->clear();
// Toutes les données sont supprimées du storage
```

## Cas d'utilisation

### Cas 1 : Correction orthographique simple

```php
use AndyDefer\AlgoKIT\Algorithms\BKTree;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;

$storage = new MemoryStorage();
$bkTree = new BKTree($storage);

// Indexer un dictionnaire
$words = ['php', 'python', 'javascript', 'laravel', 'symfony', 'react', 'vue'];
foreach ($words as $word) {
    $bkTree->insert($word);
}

// Correction d'une faute de frappe
$corrections = $bkTree->search('larvel', 2);
foreach ($corrections as $result) {
    echo "{$result->word} (distance: {$result->distance})\n";
}
// Sortie: laravel (distance: 1)
```

### Cas 2 : Suggestions avec contexte utilisateur

```php
// Mots fréquemment recherchés par un utilisateur
$userWords = ['laravel', 'eloquent', 'blade', 'php', 'artisan'];

$bkTree = new BKTree($storage, 'user_' . $userId);

foreach ($userWords as $word) {
    $bkTree->insert($word);
}

// Suggestions personnalisées
$typo = 'elokent';
$suggestions = $bkTree->search($typo, 2, 3);
// Retourne: eloquent (distance: 2)
```

### Cas 3 : Système de recherche avec correction automatique

```php
class SearchEngine
{
    private BKTree $bkTree;
    
    public function __construct(BKTree $bkTree)
    {
        $this->bkTree = $bkTree;
    }
    
    public function search(string $query): array
    {
        // 1. Recherche exacte
        $results = $this->performExactSearch($query);
        
        if (count($results) === 0) {
            // 2. Correction automatique
            $suggestions = $this->bkTree->search($query, 2, 5);
            
            if (!empty($suggestions)) {
                // 3. Recherche avec le mot corrigé
                $corrected = $suggestions->first()->word;
                $results = $this->performExactSearch($corrected);
            }
        }
        
        return $results;
    }
}
```

## Flux d'exécution

```
insert($word)
    ↓
getRoot() → null?
    ├── Oui → saveRoot(new BKTreeNodeRecord($word))
    └── Non → insertNode($root, $word)
         ↓
    distance = levenshtein(node->word, $word)
         ↓
    distance = 0? → return
         ↓
    find existing child? → return
         ↓
    find child at distance? → insertNode(child, $word)
         ↓
    add new child → children->add(new BKTreeNodeRecord)
         ↓
    saveRoot($root)
```

```
search($word, $tolerance, $limit)
    ↓
getRoot() → null? → return empty collection
    ↓
searchNode($root, $word, $tolerance, &$results)
    ↓
distance = levenshtein(node->word, $word)
    ↓
distance <= tolerance? → add to results
    ↓
min = distance - tolerance, max = distance + tolerance
    ↓
foreach child where childDistance between min and max
    ↓
    searchNode(child, $word, $tolerance, &$results)
    ↓
sort results by distance
    ↓
return first $limit results
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Aucune exception explicite | - | - |

**Note :** BKTree n'utilise pas d'exceptions pour son fonctionnement normal. Les erreurs potentielles sont gérées silencieusement (ex: recherche sur un arbre vide → collection vide).

## Intégration

### Avec Storage

BKTree utilise `StorageInterface` pour la persistance des données :

```php
// Sauvegarde automatique
$bkTree->insert('laravel'); // Persiste dans storage

// Récupération automatique
$bkTree = new BKTree($storage, 'bktree'); // Charge depuis storage
```

### Avec les Records

BKTree utilise des Records pour représenter les données :

- `BKTreeNodeRecord` : Représente un nœud de l'arbre
- `BKTreeResultRecord` : Représente un résultat de recherche

### Avec les Collections

BKTree utilise des Collections typées :

- `BKTreeNodeCollection` : Collection de nœuds
- `BKTreeResultCollection` : Collection de résultats

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `insert()` | O(log n) * | Dépend de la distribution des mots |
| `search()` | O(n) | Parcourt les nœuds dans la plage de tolérance |
| `clear()` | O(1) | Suppression de la clé dans le storage |

**Optimisations :**
- La tolérance réduit le nombre de nœuds parcourus
- La structure de l'arbre permet de sauter des branches entières
- `levenshtein()` est la fonction coûteuse (O(m*n) sur les mots)

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

use AndyDefer\AlgoKIT\Algorithms\BKTree;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;

// 1. Initialisation
$storage = new MemoryStorage();
$bkTree = new BKTree($storage, 'dictionary');

// 2. Indexation du dictionnaire
$dictionary = [
    'php', 'python', 'javascript', 'typescript',
    'laravel', 'symfony', 'react', 'vue',
    'docker', 'kubernetes', 'redis', 'postgresql'
];

foreach ($dictionary as $word) {
    $bkTree->insert($word);
}

// 3. Correction de fautes
$typos = [
    'larvel' => 'laravel',
    'pyton' => 'python',
    'javascrpt' => 'javascript',
    'symfony' => 'symfony'
];

foreach ($typos as $typo => $expected) {
    $results = $bkTree->search($typo, 2, 1);
    
    if ($results->isNotEmpty()) {
        $suggestion = $results->first()->word;
        $distance = $results->first()->distance;
        
        echo "Typo: '$typo' → Suggestion: '$suggestion' (distance: $distance)\n";
    } else {
        echo "Typo: '$typo' → Aucune suggestion\n";
    }
}

// 4. Recherche avec plusieurs suggestions
$results = $bkTree->search('dockr', 3, 3);
echo "\nSuggestions pour 'dockr':\n";
foreach ($results as $result) {
    echo "- {$result->word} (distance: {$result->distance})\n";
}

// 5. Nettoyage
$bkTree->clear();
```

**Sortie attendue :**
```
Typo: 'larvel' → Suggestion: 'laravel' (distance: 1)
Typo: 'pyton' → Suggestion: 'python' (distance: 1)
Typo: 'javascrpt' → Suggestion: 'javascript' (distance: 2)
Typo: 'symfony' → Suggestion: 'symfony' (distance: 0)

Suggestions pour 'dockr':
- docker (distance: 1)
- postgresql (distance: 6)
- kubernetes (distance: 7)
```

## Voir aussi

- `TreeInterface` - Interface pour les arbres
- `BKTreeNodeRecord` - Record représentant un nœud
- `BKTreeResultRecord` - Record pour les résultats
- `BKTreeNodeCollection` - Collection de nœuds
- `BKTreeResultCollection` - Collection de résultats
- `StorageInterface` - Interface de persistance
- `MemoryStorage` - Implémentation mémoire du storage