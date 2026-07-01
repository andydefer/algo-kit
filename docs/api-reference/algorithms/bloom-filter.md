# BloomFilter - Référence Technique

## Description

Le BloomFilter est une structure de données probabiliste permettant de tester l'appartenance d'un élément à un ensemble, avec une mémoire extrêmement réduite. Il peut produire des faux positifs mais jamais de faux négatifs.

## Hiérarchie / Implémentations

```
BloomFilterInterface
    └── BloomFilter (final)
```

La classe implémente l'interface `BloomFilterInterface` et utilise :
- `StorageInterface` pour la persistance des données
- `BloomFilterCollection` pour les opérations batch
- `BloomFilterResultCollection` pour retourner les résultats
- `BloomFilterResultRecord` pour représenter un résultat

## Rôle principal

Le BloomFilter est conçu pour répondre à la question **"Est-ce que cet élément est déjà présent ?"** de manière extrêmement efficace en mémoire. Contrairement à une table de hachage qui stocke les éléments réels, le BloomFilter stocke seulement un tableau de bits et utilise plusieurs fonctions de hachage.

**Propriétés fondamentales :**
- ✅ **Pas de faux négatifs** : Si `exists()` retourne `false`, l'élément n'est **certainement pas** dans l'ensemble
- ⚠️ **Faux positifs possibles** : Si `exists()` retourne `true`, l'élément **probablement** dans l'ensemble
- 💾 **Mémoire constante** : La mémoire utilisée ne dépend pas du nombre d'éléments

## Installation

```bash
composer require andydefer/algo-kit
```

Prérequis :
- PHP 8.1 ou supérieur
- Extension `storage-kit` installée

## API / Méthodes publiques

### `__construct(StorageInterface $storage, int $size = 10000, int $hashCount = 3, string $key = 'bloom')`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$storage` | `StorageInterface` | Backend de stockage pour la persistance |
| `$size` | `int` | Nombre de bits (plus grand = moins de faux positifs) |
| `$hashCount` | `int` | Nombre de fonctions de hachage (plus grand = moins de faux positifs) |
| `$key` | `string` | Clé unique identifiant le filtre (défaut : 'bloom') |

**Retourne :** `void`

**Exemple :**
```php
$storage = new MemoryStorage();
$bloom = new BloomFilter($storage, 100000, 5, 'url_checker');
```

---

### `insert(string $value, ?string $context = null): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `string` | La valeur à insérer dans le filtre |
| `$context` | `string|null` | Contexte optionnel pour isoler les données |

**Retourne :** `void`

**Exemple :**
```php
$bloom->insert('user_123');
$bloom->insert('user_456', 'active_users');
```

---

### `exists(string $value, ?string $context = null): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `string` | La valeur à tester |
| `$context` | `string|null` | Contexte optionnel pour isoler les données |

**Retourne :** `bool` - `true` si l'élément existe probablement, `false` s'il n'existe certainement pas

**Exemple :**
```php
if ($bloom->exists('user_123')) {
    echo "Utilisateur probablement déjà vu\n";
} else {
    echo "Utilisateur certainement jamais vu\n";
}
```

---

### `insertBatch(BloomFilterCollection $collection): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$collection` | `BloomFilterCollection` | Collection de valeurs à insérer |

**Retourne :** `void`

**Exemple :**
```php
$collection = new BloomFilterCollection();
$collection->add(new BloomFilterRecord('url1.com'));
$collection->add(new BloomFilterRecord('url2.com'));
$collection->add(new BloomFilterRecord('url3.com', 'crawled'));

$bloom->insertBatch($collection);
```

---

### `existsBatch(BloomFilterCollection $collection): BloomFilterResultCollection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$collection` | `BloomFilterCollection` | Collection de valeurs à tester |

**Retourne :** `BloomFilterResultCollection` - Collection des résultats de test

**Exemple :**
```php
$collection = new BloomFilterCollection();
$collection->add(new BloomFilterRecord('url1.com'));
$collection->add(new BloomFilterRecord('url2.com'));

$results = $bloom->existsBatch($collection);
foreach ($results as $result) {
    echo "{$result->value} : " . ($result->exists ? '✅' : '❌') . "\n";
}
```

---

### `clear(?string $context = null): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$context` | `string|null` | Si fourni, supprime seulement ce contexte |

**Retourne :** `void`

**Exemple :**
```php
// Supprimer un contexte spécifique
$bloom->clear('active_users');

// Supprimer tout le filtre
$bloom->clear();
```

---

## Cas d'utilisation

### Cas 1 : Vérification d'URLs déjà crawlé (web scraping)

```php
<?php

declare(strict_types=1);

use AndyDefer\AlgoKIT\Algorithms\BloomFilter;
use AndyDefer\StorageKit\Storage\MemoryStorage;

$storage = new MemoryStorage();
$crawledUrls = new BloomFilter($storage, 1000000, 5, 'crawler');

// URLs à explorer
$urlsToVisit = [
    'https://example.com/page1',
    'https://example.com/page2',
    'https://example.com/page1', // Déjà visitée
    'https://example.com/page3',
    'https://example.com/page2', // Déjà visitée
];

foreach ($urlsToVisit as $url) {
    if ($crawledUrls->exists($url)) {
        echo "⏭️  $url déjà crawlé, ignoré\n";
        continue;
    }
    
    echo "🕷️  Crawl de $url\n";
    // Simuler le crawling
    $crawledUrls->insert($url);
}

// Sortie :
// 🕷️  Crawl de https://example.com/page1
// 🕷️  Crawl de https://example.com/page2
// ⏭️  https://example.com/page1 déjà crawlé, ignoré
// 🕷️  Crawl de https://example.com/page3
// ⏭️  https://example.com/page2 déjà crawlé, ignoré
```

### Cas 2 : Détection de spam (URLs malveillantes)

```php
<?php

declare(strict_types=1);

use AndyDefer\AlgoKIT\Algorithms\BloomFilter;
use AndyDefer\AlgoKIT\Collections\BloomFilterCollection;
use AndyDefer\AlgoKIT\Records\BloomFilterRecord;
use AndyDefer\StorageKit\Storage\MemoryStorage;

$storage = new MemoryStorage();
$spamDomains = new BloomFilter($storage, 500000, 4, 'spam');

// Charger une liste de domaines spams connus
$knownSpam = [
    'spam-domain1.com',
    'spam-domain2.net',
    'spam-domain3.org',
    'malware-site.com',
];

foreach ($knownSpam as $domain) {
    $spamDomains->insert($domain);
}

// Tester un commentaire
$comment = "Check out my website: spam-domain1.com";
preg_match_all('/[a-zA-Z0-9-]+\\.[a-zA-Z]{2,}/', $comment, $matches);

foreach ($matches[0] as $domain) {
    if ($spamDomains->exists($domain)) {
        echo "⚠️  Domaine spam détecté : $domain\n";
        echo "Commentaire rejeté !\n";
        break;
    }
}
// Sortie :
// ⚠️  Domaine spam détecté : spam-domain1.com
// Commentaire rejeté !
```

### Cas 3 : Suivi d'utilisateurs uniques (analytics)

```php
<?php

declare(strict_types=1);

use AndyDefer\AlgoKIT\Algorithms\BloomFilter;
use AndyDefer\StorageKit\Storage\MemoryStorage;

$storage = new MemoryStorage();
$visitors = new BloomFilter($storage, 50000, 3, 'analytics');

// Simuler des visites sur 7 jours
$days = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
$totalVisits = 0;
$uniqueVisitors = 0;

foreach ($days as $day) {
    echo "\n📊 $day :\n";
    
    // Simuler 100 visites par jour
    for ($i = 0; $i < 100; $i++) {
        $userId = 'user_' . rand(1, 50);
        $totalVisits++;
        
        if ($visitors->exists($userId, $day)) {
            // Utilisateur déjà vu aujourd'hui
            continue;
        }
        
        $visitors->insert($userId, $day);
        $uniqueVisitors++;
        echo "  Nouvel utilisateur : $userId\n";
    }
}

echo "\n📈 Statistiques :\n";
echo "Visites totales : $totalVisits\n";
echo "Visiteurs uniques estimés : $uniqueVisitors\n";
echo "Taux de répétition : " . round((1 - $uniqueVisitors / $totalVisits) * 100, 1) . "%\n";
```

### Cas 4 : Cache bloqué (éviter les requêtes inutiles)

```php
<?php

declare(strict_types=1);

use AndyDefer\AlgoKIT\Algorithms\BloomFilter;
use AndyDefer\StorageKit\Storage\MemoryStorage;

// Classe d'exemple pour une API
class ApiService
{
    private BloomFilter $cacheChecker;
    private array $cache = [];
    
    public function __construct()
    {
        $storage = new MemoryStorage();
        $this->cacheChecker = new BloomFilter($storage, 10000, 3, 'api_cache');
    }
    
    public function fetchData(string $id): string
    {
        // Vérification rapide dans le BloomFilter
        if ($this->cacheChecker->exists($id)) {
            // Probablement en cache, vérifier
            if (isset($this->cache[$id])) {
                echo "📦 Cache hit pour $id\n";
                return $this->cache[$id];
            }
        }
        
        // Simuler une requête API lente
        echo "🌐 Requête API pour $id\n";
        $data = "Données pour $id";
        
        // Mettre en cache
        $this->cache[$id] = $data;
        $this->cacheChecker->insert($id);
        
        return $data;
    }
}

$api = new ApiService();
$api->fetchData('user_1');
$api->fetchData('user_2');
$api->fetchData('user_1'); // Cache hit !
$api->fetchData('user_3');
$api->fetchData('user_2'); // Cache hit !
// Sortie :
// 🌐 Requête API pour user_1
// 🌐 Requête API pour user_2
// 📦 Cache hit pour user_1
// 🌐 Requête API pour user_3
// 📦 Cache hit pour user_2
```

## Flux d'exécution

### Insertion d'une valeur

```
insert($value, $context)
    ↓
getBits($context) → Récupérer le tableau de bits
    ↓
Pour i = 0 à hashCount - 1 :
    index = hashValue($value, i) → Calculer l'index
    bits[$index] = 1 → Définir le bit
    ↓
saveBits($bits, $context) → Persister
```

### Test d'existence

```
exists($value, $context)
    ↓
getBits($context) → Récupérer le tableau de bits
    ↓
Pour i = 0 à hashCount - 1 :
    index = hashValue($value, i) → Calculer l'index
        ↓
    bits[$index] === 0 ?
        ├── OUI → Retourner false (certainement absent)
        └── NON → Continuer
    ↓
Retourner true (probablement présent)
```

### Opérations Batch (optimisées)

```
insertBatch($collection)
    ↓
Pour chaque élément :
    1. Récupérer les bits du contexte
    2. Mettre à jour les bits
    3. Sauvegarder immédiatement
    ↓
existsBatch($collection)
    ↓
Pour chaque élément :
    1. Récupérer les bits du contexte (avec cache)
    2. Tester l'existence
    3. Ajouter le résultat
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
use AndyDefer\AlgoKIT\Algorithms\BloomFilter;

// Stockage en mémoire (pour les tests)
$memoryStorage = new MemoryStorage();
$bloom = new BloomFilter($memoryStorage);

// Stockage persistant
$cacheStorage = new CacheStorage('redis');
$bloom = new BloomFilter($cacheStorage, 1000000, 5, 'production_bloom');
```

### Avec les collections

```php
use AndyDefer\AlgoKIT\Collections\BloomFilterCollection;
use AndyDefer\AlgoKIT\Records\BloomFilterRecord;
use AndyDefer\AlgoKIT\Records\BloomFilterResultRecord;

$collection = new BloomFilterCollection();
$collection->add(new BloomFilterRecord('value1'));
$collection->add(new BloomFilterRecord('value2', 'context'));

$results = $bloom->existsBatch($collection);

// Filtrer les résultats positifs
$found = $results->filter(
    fn(BloomFilterResultRecord $r) => $r->exists === true
);
```

## Performance

| Opération | Complexité | Description |
|-----------|------------|-------------|
| `insert()` | O(1) | k hachages (k = hashCount) |
| `exists()` | O(1) | k hachages maximum (arrêt anticipé) |
| `insertBatch()` | O(n × k) | n = nombre d'éléments, k = hashCount |
| `existsBatch()` | O(n × k) | Avec cache des contextes |

**Caractéristiques :**
- **Mémoire constante** : Utilise toujours `size` bits, quel que soit le nombre d'éléments
- **Temps constant** : Les opérations ne dépendent pas du nombre d'éléments
- **Scalabilité** : Peut gérer des milliards d'éléments avec peu de mémoire

**Paramètres recommandés :**

| Usage | Size | HashCount | Faux positifs |
|-------|------|-----------|---------------|
| Petits ensembles (< 1000) | 10 000 | 3 | ~1% |
| Ensembles moyens (< 1M) | 100 000 | 5 | ~0.5% |
| Grands ensembles (< 10M) | 1 000 000 | 7 | ~0.1% |
| Très grands ensembles (< 100M) | 10 000 000 | 9 | ~0.01% |

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

use AndyDefer\AlgoKIT\Algorithms\BloomFilter;
use AndyDefer\AlgoKIT\Collections\BloomFilterCollection;
use AndyDefer\AlgoKIT\Records\BloomFilterRecord;
use AndyDefer\StorageKit\Storage\MemoryStorage;

// 1. Initialisation
$storage = new MemoryStorage();
$bloom = new BloomFilter($storage, 1000, 3, 'demo');

// 2. Insertion de valeurs
$values = ['php', 'laravel', 'python', 'javascript', 'ruby'];

echo "📝 Insertion des valeurs :\n";
foreach ($values as $value) {
    $bloom->insert($value);
    echo "  ✓ $value\n";
}

// 3. Test d'existence
echo "\n🔍 Test d'existence :\n";
$testValues = ['php', 'golang', 'python', 'c++'];

foreach ($testValues as $value) {
    $exists = $bloom->exists($value);
    $status = $exists ? '✅ Présent (probablement)' : '❌ Absent (certainement)';
    echo "  $value : $status\n";
}

// 4. Opérations en batch
echo "\n📦 Opérations batch :\n";
$collection = new BloomFilterCollection();
$collection->add(new BloomFilterRecord('ruby'));
$collection->add(new BloomFilterRecord('csharp'));
$collection->add(new BloomFilterRecord('python'));

$results = $bloom->existsBatch($collection);
foreach ($results as $result) {
    $status = $result->exists ? '✅' : '❌';
    echo "  $status {$result->value}\n";
}

// 5. Nettoyage
echo "\n🧹 Nettoyage...\n";
$bloom->clear();
echo "Filtre vidé !\n";

// Exemple de sortie :
// 📝 Insertion des valeurs :
//   ✓ php
//   ✓ laravel
//   ✓ python
//   ✓ javascript
//   ✓ ruby
// 
// 🔍 Test d'existence :
//   php : ✅ Présent (probablement)
//   golang : ❌ Absent (certainement)
//   python : ✅ Présent (probablement)
//   c++ : ❌ Absent (certainement)
// 
// 📦 Opérations batch :
//   ✅ ruby
//   ❌ csharp
//   ✅ python
// 
// 🧹 Nettoyage...
// Filtre vidé !
```

## Voir aussi

- [`CountMinSketch`](count-min-sketch.md) - Compteur probabiliste de fréquences
- [`BKTree`](bk-tree.md) - Recherche floue par distance de Levenshtein
- [`HyperLogLog`](hyper-log-log.md) - Estimation de cardinalité
- [`TopK`](top-k.md) - Suivi des éléments les plus fréquents
- [`Trie`](trie.md) - Recherche par préfixe