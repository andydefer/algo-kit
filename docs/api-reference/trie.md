# Trie - Référence Technique

## Description

Trie (ou arbre préfixe) est une structure de données arborescente pour stocker des chaînes de caractères. Elle permet de rechercher efficacement tous les mots commençant par un préfixe donné, ce qui la rend idéale pour l'autocomplétion et les suggestions en temps réel.

## Hiérarchie / Implémentations

```
TrieInterface
    └── Trie
```

**Interfaces implémentées :** `TrieInterface`

## Rôle principal

Le Trie organise les mots dans une structure arborescente où chaque nœud représente un caractère. Les mots partageant un préfixe commun partagent le même chemin dans l'arbre. Cette organisation permet une recherche de préfixe en O(1) pour les opérations de base et O(n) pour l'énumération des mots, où n est la longueur du préfixe.

## Installation

```bash
composer require andydefer/algokit
```

## API / Méthodes publiques

### `__construct(StorageInterface $storage, string $key = 'trie')`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$storage` | `StorageInterface` | Instance du système de stockage |
| `$key` | `string` | Clé d'identification dans le storage (défaut: 'trie') |

**Retourne :** `void`

**Exemple :**
```php
$storage = new MemoryStorage();
$trie = new Trie($storage, 'autocomplete');
```

---

### `insert(string $word): void`

Insère un mot dans le trie.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$word` | `string` | Mot à insérer |

**Retourne :** `void`

**Exemple :**
```php
$trie->insert('laravel');
$trie->insert('laragon');
$trie->insert('large');
```

---

### `search(string $prefix, int $limit = 10): TrieResultCollection`

Recherche tous les mots commençant par un préfixe donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$prefix` | `string` | Préfixe à rechercher |
| `$limit` | `int` | Nombre maximum de résultats (défaut: 10) |

**Retourne :** `TrieResultCollection` - Collection des mots trouvés

**Exemple :**
```php
$results = $trie->search('lar', 5);
foreach ($results as $result) {
    echo $result->word . "\n";
}
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
$collection->add(new TrieRecord('laravel'));
$collection->add(new TrieRecord('python'));
$trie->insertBatch($collection);
```

---

### `searchBatch(TrieCollection $collection, int $limit = 10): array`

Recherche plusieurs préfixes en lot.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$collection` | `TrieCollection` | Collection de préfixes à rechercher |
| `$limit` | `int` | Nombre maximum de résultats par préfixe (défaut: 10) |

**Retourne :** `array<string, TrieResultCollection>` - Tableau associatif préfixe → résultats

**Exemple :**
```php
$results = $trie->searchBatch($collection);
foreach ($results as $prefix => $words) {
    echo "Préfixe '$prefix': " . count($words) . " mots\n";
}
```

---

### `clear(): void`

Vide complètement le trie.

**Retourne :** `void`

**Exemple :**
```php
$trie->clear();
```

## Cas d'utilisation

### Cas 1 : Autocomplétion de recherche

```php
use AndyDefer\AlgoKIT\Algorithms\Trie;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;

$storage = new MemoryStorage();
$trie = new Trie($storage, 'search_autocomplete');

// Indexation des termes de recherche
$searchTerms = [
    'laravel', 'laragon', 'large', 'laptop',
    'php', 'python', 'puppet', 'pascal',
    'javascript', 'java', 'jupyter'
];

foreach ($searchTerms as $term) {
    $trie->insert($term);
}

// Autocomplétion en temps réel
function autocomplete(Trie $trie, string $input): array
{
    $results = $trie->search($input, 5);
    return array_map(function($result) {
        return $result->word;
    }, $results->toArray());
}

echo "Suggestions pour 'la': " . implode(', ', autocomplete($trie, 'la')) . "\n";
echo "Suggestions pour 'p': " . implode(', ', autocomplete($trie, 'p')) . "\n";
echo "Suggestions pour 'j': " . implode(', ', autocomplete($trie, 'j')) . "\n";
```

### Cas 2 : Dictionnaire avec poids

```php
class WeightedDictionary
{
    private Trie $trie;
    private array $weights = [];
    
    public function __construct(Trie $trie)
    {
        $this->trie = $trie;
    }
    
    public function addWord(string $word, int $weight = 1): void
    {
        $this->trie->insert($word);
        $this->weights[$word] = ($this->weights[$word] ?? 0) + $weight;
    }
    
    public function suggest(string $prefix, int $limit = 10): array
    {
        $results = $this->trie->search($prefix, $limit * 2);
        
        $suggestions = [];
        foreach ($results as $result) {
            $word = $result->word;
            $suggestions[$word] = $this->weights[$word] ?? 0;
        }
        
        arsort($suggestions);
        return array_slice(array_keys($suggestions), 0, $limit);
    }
}

// Utilisation
$storage = new MemoryStorage();
$trie = new Trie($storage, 'weighted_dict');
$dict = new WeightedDictionary($trie);

// Ajout de mots avec poids
$dict->addWord('laravel', 10);
$dict->addWord('laragon', 5);
$dict->addWord('large', 3);
$dict->addWord('laptop', 8);
$dict->addWord('php', 15);

echo "Suggestions pondérées pour 'la':\n";
print_r($dict->suggest('la', 3));
```

### Cas 3 : Moteur de recherche multi-préfixes

```php
class MultiPrefixSearch
{
    private Trie $trie;
    
    public function __construct(Trie $trie)
    {
        $this->trie = $trie;
    }
    
    public function indexDocuments(array $documents): void
    {
        $collection = new TrieCollection();
        
        foreach ($documents as $doc) {
            $words = explode(' ', strtolower($doc['content']));
            foreach ($words as $word) {
                $word = trim(preg_replace('/[^a-zA-Z0-9]/', '', $word));
                if (!empty($word)) {
                    $collection->add(new TrieRecord($word));
                }
            }
        }
        
        $this->trie->insertBatch($collection);
    }
    
    public function searchPrefixes(array $prefixes, int $limit = 5): array
    {
        $collection = new TrieCollection();
        foreach ($prefixes as $prefix) {
            $collection->add(new TrieRecord(strtolower($prefix)));
        }
        
        $results = $this->trie->searchBatch($collection, $limit);
        
        $formatted = [];
        foreach ($results as $prefix => $words) {
            $formatted[$prefix] = array_map(function($result) {
                return $result->word;
            }, $words->toArray());
        }
        
        return $formatted;
    }
}

// Utilisation
$storage = new MemoryStorage();
$trie = new Trie($storage, 'document_search');
$engine = new MultiPrefixSearch($trie);

$documents = [
    ['id' => 1, 'content' => 'PHP is a popular programming language'],
    ['id' => 2, 'content' => 'Laravel is a PHP framework'],
    ['id' => 3, 'content' => 'Python is used for data science'],
    ['id' => 4, 'content' => 'JavaScript is for web development']
];

$engine->indexDocuments($documents);

$results = $engine->searchPrefixes(['p', 'la', 'ja', 'web']);
echo "Résultats de recherche:\n";
foreach ($results as $prefix => $words) {
    echo "  '$prefix': " . implode(', ', $words) . "\n";
}
```

## Flux d'exécution

```
insert($word)
    ↓
getRoot() → nœud racine
    ↓
node = &root
    ↓
for each char in word
    ↓
    char exists in node->children?
        ├── Non → create new node
        └── Oui → use existing node
    ↓
    node = &node->children[char]
    ↓
word already in node->words?
    ├── Non → add word to node->words
    └── Oui → ignore
    ↓
saveRoot($root)
```

```
search($prefix, $limit)
    ↓
getRoot() → nœud racine
    ↓
findNode($root, $prefix) → node
    ↓
node === null? → return empty collection
    ↓
collectWords($node, $prefix, $limit)
    ↓
for each word in node->words
    ↓
    add to results
    ↓
for each child
    ↓
    collectWords(child, prefix + char, limit)
    ↓
return TrieResultCollection
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Aucune exception explicite | - | - |

**Note :** Trie ne lève pas d'exceptions. Les erreurs sont gérées silencieusement par l'utilisation de valeurs par défaut.

## Intégration

### Avec Storage

Trie utilise `StorageInterface` pour la persistance des données :

```php
// Sauvegarde automatique
$trie->insert('word'); // Persiste dans storage

// Récupération automatique
$trie = new Trie($storage, 'trie'); // Charge depuis storage
```

### Avec les Records

Trie utilise des Records pour représenter les données :

- `TrieRecord` : Représente un mot à insérer
- `TrieResultRecord` : Représente un résultat de recherche

### Avec les Collections

Trie utilise des Collections typées :

- `TrieCollection` : Collection de mots
- `TrieResultCollection` : Collection de résultats

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `insert()` | O(n) | n = longueur du mot |
| `search()` | O(n + m) | n = longueur du préfixe, m = nombre de résultats |
| `insertBatch()` | O(N) | N = longueur totale des mots |
| `searchBatch()` | O(p * (n + m)) | p = nombre de préfixes |
| `clear()` | O(1) | Suppression de la clé dans le storage |

**Optimisations :**
- Les mots partagent des préfixes communs
- La recherche est limitée par le nombre de résultats
- La structure est optimisée pour les lectures

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

use AndyDefer\AlgoKIT\Algorithms\Trie;
use AndyDefer\AlgoKIT\Collections\TrieCollection;
use AndyDefer\AlgoKIT\Records\TrieRecord;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;

// 1. Initialisation
$storage = new MemoryStorage();
$trie = new Trie($storage, 'test_trie');

echo "=== Test du Trie ===\n\n";

// 2. Insertion de mots
echo "2. Insertion de mots:\n";
$words = [
    'laravel', 'laragon', 'large', 'laptop', 'lightning',
    'php', 'python', 'perl', 'pascal', 'puppet',
    'javascript', 'java', 'jupyter', 'json'
];

foreach ($words as $word) {
    $trie->insert($word);
    echo "  + $word\n";
}

echo "\n";

// 3. Recherche simple
echo "3. Recherche simple:\n";
$prefixes = ['la', 'p', 'j'];

foreach ($prefixes as $prefix) {
    $results = $trie->search($prefix, 5);
    $words = array_map(function($result) {
        return $result->word;
    }, $results->toArray());
    echo "  '$prefix': " . implode(', ', $words) . "\n";
}

echo "\n";

// 4. Recherche avec limite
echo "4. Recherche avec limite:\n";
$results = $trie->search('p', 3);
$words = array_map(function($result) {
    return $result->word;
}, $results->toArray());
echo "  'p' (limite 3): " . implode(', ', $words) . "\n";

echo "\n";

// 5. Insertion par lot
echo "5. Insertion par lot:\n";
$collection = new TrieCollection();
$newWords = ['react', 'vue', 'angular', 'svelte'];

foreach ($newWords as $word) {
    $collection->add(new TrieRecord($word));
}

$trie->insertBatch($collection);
echo "  + " . implode(', ', $newWords) . " (insérés en lot)\n";

echo "\n";

// 6. Recherche par lot
echo "6. Recherche par lot:\n";
$searchCollection = new TrieCollection();
$searchWords = ['r', 'v', 'a', 's'];

foreach ($searchWords as $word) {
    $searchCollection->add(new TrieRecord($word));
}

$results = $trie->searchBatch($searchCollection, 3);
foreach ($results as $prefix => $resultCollection) {
    $words = array_map(function($result) {
        return $result->word;
    }, $resultCollection->toArray());
    echo "  '$prefix': " . implode(', ', $words) . "\n";
}

echo "\n";

// 7. Persistance
echo "7. Test de persistance:\n";
$trie2 = new Trie($storage, 'test_trie');
$results = $trie2->search('l', 5);
$words = array_map(function($result) {
    return $result->word;
}, $results->toArray());
echo "  Mots en 'l' après récupération: " . implode(', ', $words) . "\n";

echo "\n";

// 8. Statistiques
echo "8. Statistiques:\n";
$allResults = $trie->search('', 1000);
echo "  Total des mots indexés: " . count($allResults) . "\n";

echo "\n";

// 9. Nettoyage
$trie->clear();
echo "9. ✓ Trie vidé\n";

$emptyResults = $trie->search('l');
echo "  Mots après vidage: " . count($emptyResults) . "\n";
```

**Sortie attendue :**
```
=== Test du Trie ===

2. Insertion de mots:
  + laravel
  + laragon
  + large
  + laptop
  + lightning
  + php
  + python
  + perl
  + pascal
  + puppet
  + javascript
  + java
  + jupyter
  + json

3. Recherche simple:
  'la': laravel, laragon, large, laptop
  'p': php, python, perl, pascal, puppet
  'j': javascript, java, jupyter, json

4. Recherche avec limite:
  'p' (limite 3): php, python, perl

5. Insertion par lot:
  + react, vue, angular, svelte (insérés en lot)

6. Recherche par lot:
  'r': react
  'v': vue
  'a': angular
  's': svelte

7. Test de persistance:
  Mots en 'l' après récupération: laravel, laragon, large, laptop, lightning

8. Statistiques:
  Total des mots indexés: 18

9. ✓ Trie vidé
  Mots après vidage: 0
```

## Voir aussi

- `TrieInterface` - Interface du Trie
- `TrieRecord` - Record pour les mots
- `TrieResultRecord` - Record pour les résultats
- `TrieCollection` - Collection de mots
- `TrieResultCollection` - Collection de résultats
- `StorageInterface` - Interface de persistance
- `MemoryStorage` - Implémentation mémoire du storage
- `BKTree` - Structure pour la correction orthographique
- `BloomFilter` - Structure pour le test d'existence