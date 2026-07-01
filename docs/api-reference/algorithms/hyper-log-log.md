# HyperLogLog - Référence Technique

## Description

HyperLogLog est un algorithme probabiliste qui estime le nombre d'éléments distincts (cardinalité) dans un ensemble de données en utilisant une mémoire logarithmique. Il offre un excellent compromis entre précision et utilisation mémoire.

## Hiérarchie / Implémentations

```
HyperLogLogInterface
    └── HyperLogLog (final)
```

La classe implémente l'interface `HyperLogLogInterface` et utilise :
- `StorageInterface` pour la persistance des données
- `HyperLogLogCollection` pour les opérations batch
- `HyperLogLogResultCollection` pour retourner les résultats
- `HyperLogLogRecord` pour représenter une valeur à ajouter
- `HyperLogLogResultRecord` pour représenter un résultat de cardinalité

## Rôle principal

HyperLogLog répond à la question **"Combien d'éléments uniques contient cet ensemble ?"** sans avoir à stocker tous les éléments. Il utilise un tableau de registres et un hachage pour estimer la cardinalité avec une précision configurable. Particulièrement adapté pour le comptage d'éléments uniques dans des flux massifs de données (utilisateurs uniques, adresses IP distinctes, mots-clés uniques, etc.).

**Propriétés fondamentales :**
- ✅ **Mémoire logarithmique** : O(log log n) en fonction du nombre d'éléments
- ✅ **Précision configurable** : Ajustable via le paramètre `precision`
- ✅ **Temps constant** : Les opérations sont en O(1)
- ✅ **Parallélisable** : Les contextes peuvent être fusionnés
- ⚠️ **Approximatif** : Résultat avec une marge d'erreur contrôlée

## Installation

```bash
composer require andydefer/algo-kit
```

Prérequis :
- PHP 8.1 ou supérieur
- Extension `storage-kit` installée

## API / Méthodes publiques

### `__construct(StorageInterface $storage, int $precision = 16, string $key = 'hll')`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$storage` | `StorageInterface` | Backend de stockage pour la persistance |
| `$precision` | `int` | Nombre de bits (4-16, plus grand = plus précis) |
| `$key` | `string` | Clé unique identifiant l'instance (défaut : 'hll') |

**Retourne :** `void`

**Exemple :**
```php
$storage = new MemoryStorage();
$hll = new HyperLogLog($storage, 16, 'unique_visitors');
```

---

### `add(string $value, ?string $context = null): void`

Ajoute une valeur à l'ensemble HyperLogLog.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `string` | La valeur à ajouter |
| `$context` | `string|null` | Contexte optionnel pour isoler les données |

**Retourne :** `void`

**Exemple :**
```php
$hll->add('user_123');
$hll->add('user_456', 'active_users');
```

---

### `count(?string $context = null): int`

Estime le nombre d'éléments distincts.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$context` | `string|null` | Contexte optionnel à compter |

**Retourne :** `int` - Estimation de la cardinalité

**Exemple :**
```php
$total = $hll->count(); // Retourne ~2
$active = $hll->count('active_users'); // Retourne ~1
```

---

### `addBatch(HyperLogLogCollection $collection): void`

Ajoute plusieurs valeurs en lot.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$collection` | `HyperLogLogCollection` | Collection de valeurs à ajouter |

**Retourne :** `void`

**Exemple :**
```php
$collection = new HyperLogLogCollection();
$collection->add(new HyperLogLogRecord('user_123'));
$collection->add(new HyperLogLogRecord('user_456'));
$collection->add(new HyperLogLogRecord('user_123'));
$hll->addBatch($collection);
```

---

### `countBatch(HyperLogLogCollection $collection): HyperLogLogResultCollection`

Compte les éléments distincts pour plusieurs contextes en lot.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$collection` | `HyperLogLogCollection` | Collection de valeurs avec contextes |

**Retourne :** `HyperLogLogResultCollection` - Collection des résultats de cardinalité

**Exemple :**
```php
$collection = new HyperLogLogCollection();
$collection->add(new HyperLogLogRecord('x', 'context1'));
$collection->add(new HyperLogLogRecord('y', 'context2'));

$results = $hll->countBatch($collection);
foreach ($results as $result) {
    echo "{$result->context}: {$result->count}\n";
}
```

---

### `clear(?string $context = null): void`

Supprime toutes les données de l'instance HyperLogLog.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$context` | `string|null` | Si fourni, supprime seulement ce contexte |

**Retourne :** `void`

**Exemple :**
```php
// Supprimer un contexte spécifique
$hll->clear('active_users');

// Supprimer tout
$hll->clear();
```

---

## Cas d'utilisation

### Cas 1 : Comptage d'utilisateurs uniques par jour

```php
<?php

declare(strict_types=1);

use AndyDefer\AlgoKIT\Algorithms\HyperLogLog;
use AndyDefer\StorageKit\Storage\MemoryStorage;

class UniqueVisitors
{
    private HyperLogLog $hll;
    
    public function __construct(HyperLogLog $hll)
    {
        $this->hll = $hll;
    }
    
    public function trackVisit(string $userId): void
    {
        $date = date('Y-m-d');
        $this->hll->add($userId, $date);
    }
    
    public function getUniqueVisitors(string $date): int
    {
        return $this->hll->count($date);
    }
    
    public function getTotalUniqueVisitors(array $dates): int
    {
        $total = 0;
        foreach ($dates as $date) {
            $total += $this->hll->count($date);
        }
        return $total;
    }
}

// Utilisation
$storage = new MemoryStorage();
$hll = new HyperLogLog($storage, 12, 'visitors');
$visitors = new UniqueVisitors($hll);

// Simuler des visites
$visits = [
    ['user_1', '2024-01-01'],
    ['user_2', '2024-01-01'],
    ['user_1', '2024-01-01'],
    ['user_3', '2024-01-02'],
    ['user_1', '2024-01-02'],
];

foreach ($visits as [$userId, $date]) {
    $visitors->trackVisit($userId, $date);
}

echo "Visiteurs uniques le 2024-01-01 : " . $visitors->getUniqueVisitors('2024-01-01') . "\n";
echo "Visiteurs uniques le 2024-01-02 : " . $visitors->getUniqueVisitors('2024-01-02') . "\n";
// Sortie :
// Visiteurs uniques le 2024-01-01 : ~2
// Visiteurs uniques le 2024-01-02 : ~2
```

### Cas 2 : Analyse de mots-clés uniques dans les recherches

```php
<?php

declare(strict_types=1);

use AndyDefer\AlgoKIT\Algorithms\HyperLogLog;
use AndyDefer\StorageKit\Storage\MemoryStorage;

class SearchAnalytics
{
    private HyperLogLog $hll;
    
    public function __construct(HyperLogLog $hll)
    {
        $this->hll = $hll;
    }
    
    public function trackSearch(string $query, string $category): void
    {
        $this->hll->add($query, $category);
        $this->hll->add($query); // Track global
    }
    
    public function getUniqueSearches(string $category): int
    {
        return $this->hll->count($category);
    }
    
    public function getTotalUniqueSearches(): int
    {
        return $this->hll->count();
    }
}

// Utilisation
$storage = new MemoryStorage();
$hll = new HyperLogLog($storage, 14, 'search_analytics');
$analytics = new SearchAnalytics($hll);

// Simuler des recherches
$searches = [
    ['php', 'programming'],
    ['laravel', 'programming'],
    ['php', 'programming'],
    ['python', 'programming'],
    ['recipes', 'cooking'],
    ['php', 'programming'],
];

foreach ($searches as [$query, $category]) {
    $analytics->trackSearch($query, $category);
}

echo "Recherches uniques en programmation : " . $analytics->getUniqueSearches('programming') . "\n";
echo "Recherches uniques en cuisine : " . $analytics->getUniqueSearches('cooking') . "\n";
echo "Total recherches uniques : " . $analytics->getTotalUniqueSearches() . "\n";
// Sortie :
// Recherches uniques en programmation : ~3
// Recherches uniques en cuisine : ~1
// Total recherches uniques : ~4
```

### Cas 3 : Détection d'IP uniques dans les logs

```php
<?php

declare(strict_types=1);

use AndyDefer\AlgoKIT\Algorithms\HyperLogLog;
use AndyDefer\AlgoKIT\Collections\HyperLogLogCollection;
use AndyDefer\AlgoKIT\Records\HyperLogLogRecord;
use AndyDefer\StorageKit\Storage\MemoryStorage;

class TrafficAnalyzer
{
    private HyperLogLog $hll;
    
    public function __construct(HyperLogLog $hll)
    {
        $this->hll = $hll;
    }
    
    public function processLogs(array $logs): void
    {
        $collection = new HyperLogLogCollection();
        
        foreach ($logs as $log) {
            $date = date('Y-m-d', strtotime($log['timestamp']));
            $collection->add(new HyperLogLogRecord($log['ip'], $date));
        }
        
        $this->hll->addBatch($collection);
    }
    
    public function getUniqueIpsByDay(string $date): int
    {
        return $this->hll->count($date);
    }
    
    public function getTotalUniqueIps(): int
    {
        return $this->hll->count();
    }
}

// Utilisation
$storage = new MemoryStorage();
$hll = new HyperLogLog($storage, 15, 'traffic');
$analyzer = new TrafficAnalyzer($hll);

$logs = [
    ['ip' => '192.168.1.1', 'timestamp' => '2024-01-01 10:00:00'],
    ['ip' => '192.168.1.2', 'timestamp' => '2024-01-01 10:01:00'],
    ['ip' => '192.168.1.1', 'timestamp' => '2024-01-01 10:02:00'],
    ['ip' => '192.168.1.3', 'timestamp' => '2024-01-02 10:00:00'],
    ['ip' => '192.168.1.4', 'timestamp' => '2024-01-02 10:01:00'],
    ['ip' => '192.168.1.1', 'timestamp' => '2024-01-02 10:02:00'],
];

$analyzer->processLogs($logs);

echo "IPs uniques le 2024-01-01 : " . $analyzer->getUniqueIpsByDay('2024-01-01') . "\n";
echo "IPs uniques le 2024-01-02 : " . $analyzer->getUniqueIpsByDay('2024-01-02') . "\n";
echo "Total IPs uniques : " . $analyzer->getTotalUniqueIps() . "\n";
// Sortie :
// IPs uniques le 2024-01-01 : ~2
// IPs uniques le 2024-01-02 : ~3
// Total IPs uniques : ~4
```

### Cas 4 : Analyse de hashtags uniques sur les réseaux sociaux

```php
<?php

declare(strict_types=1);

use AndyDefer\AlgoKIT\Algorithms\HyperLogLog;
use AndyDefer\StorageKit\Storage\MemoryStorage;

class SocialAnalytics
{
    private HyperLogLog $hll;
    private array $hashtags = [];
    
    public function __construct(HyperLogLog $hll)
    {
        $this->hll = $hll;
    }
    
    public function trackHashtag(string $hashtag, string $language): void
    {
        $this->hll->add($hashtag, $language);
        $this->hll->add($hashtag);
        
        if (!in_array($hashtag, $this->hashtags)) {
            $this->hashtags[] = $hashtag;
        }
    }
    
    public function getUniqueHashtagsByLanguage(string $language): int
    {
        return $this->hll->count($language);
    }
    
    public function getTotalUniqueHashtags(): int
    {
        return $this->hll->count();
    }
    
    public function getUniqueHashtagsByMultipleLanguages(array $languages): int
    {
        $collection = new HyperLogLogCollection();
        
        foreach ($this->hashtags as $hashtag) {
            $collection->add(new HyperLogLogRecord($hashtag));
        }
        
        $results = $this->hll->countBatch($collection);
        $total = 0;
        
        foreach ($results as $result) {
            if (in_array($result->context, $languages)) {
                $total += $result->count;
            }
        }
        
        return $total;
    }
}

// Utilisation
$storage = new MemoryStorage();
$hll = new HyperLogLog($storage, 12, 'social_analytics');
$analytics = new SocialAnalytics($hll);

$tweets = [
    ['#php', 'en'],
    ['#laravel', 'en'],
    ['#php', 'en'],
    ['#python', 'en'],
    ['#php', 'fr'],
    ['#laravel', 'fr'],
];

foreach ($tweets as [$hashtag, $language]) {
    $analytics->trackHashtag($hashtag, $language);
}

echo "Hashtags uniques en anglais : " . $analytics->getUniqueHashtagsByLanguage('en') . "\n";
echo "Hashtags uniques en français : " . $analytics->getUniqueHashtagsByLanguage('fr') . "\n";
echo "Total hashtags uniques : " . $analytics->getTotalUniqueHashtags() . "\n";
// Sortie :
// Hashtags uniques en anglais : ~3
// Hashtags uniques en français : ~2
// Total hashtags uniques : ~3
```

## Flux d'exécution

### Ajout d'une valeur

```
add($value, $context)
    ↓
getRegisters($context) → Récupérer les registres
    ↓
hash = hashValue($value) → Hacher la valeur
    ↓
registerIndex = selectRegisterIndex($hash) → Sélectionner le registre
hashRemainder = extractHashRemainder($hash) → Extraire le reste
    ↓
rank = calculateLeadingZeros(hashRemainder) + 1 → Calculer le rang
    ↓
rank > registers[registerIndex] ?
    ├── OUI → registers[registerIndex] = rank
    └── NON → Ne rien faire
    ↓
saveRegisters($registers, $context) → Persister
```

### Estimation de cardinalité

```
count($context)
    ↓
registers = getRegisters($context)
    ↓
harmonicSum = calculateHarmonicSum($registers)
    ↓
harmonicSum == 0 ?
    ├── OUI → Retourner 0
    └── NON → Continuer
    ↓
alpha = ALPHA_CONSTANT / (1 + ALPHA_DENOMINATOR / registerCount)
estimate = alpha * registerCount² / harmonicSum
    ↓
estimate <= SMALL_SET_THRESHOLD * registerCount ?
    ├── OUI → applySmallSetCorrection($registers, $estimate)
    └── NON → Garder estimate
    ↓
Retourner (int) estimate
```

### Opérations Batch (optimisées)

```
addBatch($collection)
    ↓
Pour chaque élément :
    1. Récupérer les registres du contexte
    2. Calculer le rang
    3. Mettre à jour le registre si nécessaire
    4. Marquer le contexte comme modifié
    ↓
Pour chaque contexte modifié :
    saveRegisters($registers, $context)
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
use AndyDefer\AlgoKIT\Algorithms\HyperLogLog;

// Stockage en mémoire (pour les tests)
$memoryStorage = new MemoryStorage();
$hll = new HyperLogLog($memoryStorage);

// Stockage persistant avec cache
$cacheStorage = new CacheStorage('redis');
$hll = new HyperLogLog($cacheStorage, 14, 'production_hll');
```

### Avec les collections

```php
use AndyDefer\AlgoKIT\Collections\HyperLogLogCollection;
use AndyDefer\AlgoKIT\Collections\HyperLogLogResultCollection;
use AndyDefer\AlgoKIT\Records\HyperLogLogRecord;
use AndyDefer\AlgoKIT\Records\HyperLogLogResultRecord;

// Créer une collection de valeurs
$collection = new HyperLogLogCollection();
$collection->add(new HyperLogLogRecord('value1'));
$collection->add(new HyperLogLogRecord('value2', 'context'));

// Ajout en batch
$hll->addBatch($collection);

// Comptage en batch
$results = $hll->countBatch($collection);

// Filtrer les résultats non nuls
$nonZero = $results->filter(
    fn(HyperLogLogResultRecord $r) => $r->count > 0
);
```

### Avec les autres algorithmes

HyperLogLog peut être combiné avec d'autres algorithmes probabilistes :

- **Avec BloomFilter** : Vérifier l'appartenance avant de compter
- **Avec CountMinSketch** : Compter les fréquences des éléments uniques
- **Avec TopK** : Identifier les éléments les plus fréquents parmi les uniques

```php
use AndyDefer\AlgoKIT\Algorithms\HyperLogLog;
use AndyDefer\AlgoKIT\Algorithms\CountMinSketch;

$hll = new HyperLogLog($storage, 14, 'uniques');
$cms = new CountMinSketch($storage, 10000, 5, 'frequencies');

// Compter les éléments uniques et leurs fréquences
$items = ['a', 'b', 'a', 'c', 'b', 'a', 'd'];

foreach ($items as $item) {
    $hll->add($item);
    $cms->add($item);
}

echo "Éléments uniques : " . $hll->count() . "\n";
echo "Fréquence de 'a' : " . $cms->count('a') . "\n";
```

## Performance

| Opération | Complexité | Description |
|-----------|------------|-------------|
| `add()` | O(1) | Hachage et mise à jour d'un registre |
| `count()` | O(registerCount) | Parcours de tous les registres |
| `addBatch()` | O(n) | n = nombre d'éléments |
| `countBatch()` | O(n × registerCount) | n = nombre de contextes |

**Précision :**
- L'erreur standard est d'environ `1.04 / sqrt(registerCount)`
- Avec `precision = 16` (65536 registres), l'erreur est d'environ 0.4%
- Avec `precision = 12` (4096 registres), l'erreur est d'environ 1.6%

**Mémoire :**
- La mémoire utilisée est `2^precision` entiers
- Avec `precision = 16` : 65536 entiers ≈ 2 MB
- Avec `precision = 12` : 4096 entiers ≈ 128 KB

**Recommandations :**

| Usage | Précision | Registres | Erreur estimée | Mémoire |
|-------|-----------|-----------|----------------|---------|
| Volume < 10k | 10 | 1 024 | ~3.2% | ~32 KB |
| Volume < 1M | 12 | 4 096 | ~1.6% | ~128 KB |
| Volume < 10M | 14 | 16 384 | ~0.8% | ~512 KB |
| Volume > 10M | 16 | 65 536 | ~0.4% | ~2 MB |

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

use AndyDefer\AlgoKIT\Algorithms\HyperLogLog;
use AndyDefer\AlgoKIT\Collections\HyperLogLogCollection;
use AndyDefer\AlgoKIT\Records\HyperLogLogRecord;
use AndyDefer\StorageKit\Storage\MemoryStorage;

// 1. Initialisation
$storage = new MemoryStorage();
$hll = new HyperLogLog($storage, 12, 'demo_hll');

echo "📊 DÉMONSTRATION HYPERLOGLOG\n";
echo "═══════════════════════════════\n\n";

// 2. Ajout de données
echo "📝 Ajout de données :\n";
$data = [
    ['user_1', 'site_a'],
    ['user_2', 'site_a'],
    ['user_1', 'site_a'],
    ['user_3', 'site_b'],
    ['user_2', 'site_b'],
    ['user_4', 'site_a'],
    ['user_1', 'site_b'],
];

foreach ($data as [$userId, $site]) {
    $hll->add($userId, $site);
    echo "  + {$userId} ({$site})\n";
}

// 3. Comptage individuel
echo "\n🔍 Comptage individuel :\n";
$sites = ['site_a', 'site_b', 'site_c'];

foreach ($sites as $site) {
    $count = $hll->count($site);
    echo "  {$site} : {$count} utilisateurs uniques\n";
}

// 4. Comptage global
echo "\n📊 Comptage global :\n";
$total = $hll->count();
echo "  Total utilisateurs uniques : {$total}\n";

// 5. Opérations batch
echo "\n📦 Opérations batch :\n";

// Insertion batch
$batch = new HyperLogLogCollection();
$batch->add(new HyperLogLogRecord('user_5', 'site_a'));
$batch->add(new HyperLogLogRecord('user_6', 'site_b'));
$batch->add(new HyperLogLogRecord('user_5', 'site_b'));

$hll->addBatch($batch);
echo "  ✓ Insertion batch effectuée\n";

// Comptage batch
$query = new HyperLogLogCollection();
$query->add(new HyperLogLogRecord('x', 'site_a'));
$query->add(new HyperLogLogRecord('y', 'site_b'));

$results = $hll->countBatch($query);
foreach ($results as $result) {
    echo "  {$result->context} : {$result->count} utilisateurs uniques\n";
}

// 6. Évolution des données
echo "\n📈 Évolution :\n";

$before = $hll->count('site_a');
$hll->add('user_7', 'site_a');
$after = $hll->count('site_a');

echo "  Avant : {$before} utilisateurs\n";
echo "  Après : {$after} utilisateurs\n";

// 7. Nettoyage
echo "\n🧹 Nettoyage :\n";
$hll->clear('site_a');
echo "  ✓ Contexte 'site_a' vidé\n";

$siteACount = $hll->count('site_a');
$siteBCount = $hll->count('site_b');

echo "  Site A : {$siteACount} utilisateurs\n";
echo "  Site B : {$siteBCount} utilisateurs\n";

// Exemple de sortie :
// 📊 DÉMONSTRATION HYPERLOGLOG
// ═══════════════════════════════
// 
// 📝 Ajout de données :
//   + user_1 (site_a)
//   + user_2 (site_a)
//   + user_1 (site_a)
//   + user_3 (site_b)
//   + user_2 (site_b)
//   + user_4 (site_a)
//   + user_1 (site_b)
// 
// 🔍 Comptage individuel :
//   site_a : 3 utilisateurs uniques
//   site_b : 3 utilisateurs uniques
//   site_c : 0 utilisateurs uniques
// 
// 📊 Comptage global :
//   Total utilisateurs uniques : 4
// 
// 📦 Opérations batch :
//   ✓ Insertion batch effectuée
//   site_a : 4 utilisateurs uniques
//   site_b : 4 utilisateurs uniques
// 
// 📈 Évolution :
//   Avant : 4 utilisateurs
//   Après : 5 utilisateurs
// 
// 🧹 Nettoyage :
//   ✓ Contexte 'site_a' vidé
//   Site A : 0 utilisateurs
//   Site B : 4 utilisateurs
```

## Voir aussi

- [`bloom-filter`](bloom-filter.md) - Test probabiliste d'appartenance
- [`count-min-sketch`](count-min-sketch.md) - Compteur probabiliste de fréquences
- [`top-k`](top-k.md) - Suivi des éléments les plus fréquents
- [`bk-tree`](bk-tree.md) - Recherche floue par distance de Levenshtein
- [`trie`](trie.md) - Recherche par préfixe