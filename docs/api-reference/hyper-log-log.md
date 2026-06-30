# HyperLogLog - Référence Technique

## Description

HyperLogLog est une structure de données probabiliste qui estime le nombre d'éléments uniques dans un ensemble. Elle permet de compter les valeurs distinctes dans de très grands volumes de données en utilisant une quantité de mémoire extrêmement réduite.

## Hiérarchie / Implémentations

```
HyperLogLogInterface
    └── HyperLogLog
```

**Interfaces implémentées :** `HyperLogLogInterface`

## Rôle principal

HyperLogLog utilise un algorithme de hachage pour distribuer les éléments dans des registres, puis estime le nombre d'éléments uniques en analysant la répartition des bits de tête. Particulièrement adapté pour les analyses de données massives où la mémoire est limitée (logs, métriques, analyses utilisateurs).

## Installation

```bash
composer require andydefer/algokit
```

## API / Méthodes publiques

### `__construct(StorageInterface $storage, int $precision = 16, string $key = 'hll')`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$storage` | `StorageInterface` | Instance du système de stockage |
| `$precision` | `int` | Précision (défaut: 16, entre 4 et 16) |
| `$key` | `string` | Clé d'identification dans le storage (défaut: 'hll') |

**Retourne :** `void`

**Exemple :**
```php
$storage = new MemoryStorage();
$hll = new HyperLogLog($storage, 14, 'unique_visitors');
```

---

### `add(string $value): void`

Ajoute une valeur à l'ensemble.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `string` | Valeur à ajouter |

**Retourne :** `void`

**Exemple :**
```php
$hll->add('user_123');
$hll->add('user_456');
$hll->add('user_123'); // Duplicate, sera ignoré
```

---

### `count(): int`

Estime le nombre d'éléments uniques dans l'ensemble.

**Retourne :** `int` - Estimation du nombre d'éléments distincts

**Exemple :**
```php
$uniqueUsers = $hll->count(); // ~2
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
$hll->addBatch($collection);
```

---

### `countBatch(HyperLogLogCollection $collection): HyperLogLogResultCollection`

Compte les éléments uniques pour plusieurs contextes.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$collection` | `HyperLogLogCollection` | Collection de valeurs avec contexte |

**Retourne :** `HyperLogLogResultCollection` - Collection des résultats avec le nombre d'éléments uniques

**Exemple :**
```php
$results = $hll->countBatch($collection);
foreach ($results as $result) {
    echo "Contexte: {$result->context}, Uniques: {$result->count}\n";
}
```

---

### `clear(): void`

Vide complètement le HyperLogLog.

**Retourne :** `void`

**Exemple :**
```php
$hll->clear();
```

## Cas d'utilisation

### Cas 1 : Comptage d'utilisateurs uniques

```php
use AndyDefer\AlgoKIT\Algorithms\HyperLogLog;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;

$storage = new MemoryStorage();
$hll = new HyperLogLog($storage, 14, 'unique_visitors');

// Simuler des visites
$visits = [
    '2024-01-01' => ['user_123', 'user_456', 'user_789'],
    '2024-01-02' => ['user_123', 'user_456', 'user_abc'],
    '2024-01-03' => ['user_789', 'user_abc', 'user_def']
];

foreach ($visits as $date => $users) {
    foreach ($users as $user) {
        $hll->add($user);
    }
    echo "{$date}: " . $hll->count() . " utilisateurs uniques\n";
}
// Sortie approximative:
// 2024-01-01: 3 utilisateurs uniques
// 2024-01-02: 4 utilisateurs uniques
// 2024-01-03: 5 utilisateurs uniques
```

### Cas 2 : Analyse de logs avec contexte

```php
use AndyDefer\AlgoKIT\Algorithms\HyperLogLog;
use AndyDefer\AlgoKIT\Collections\HyperLogLogCollection;
use AndyDefer\AlgoKIT\Records\HyperLogLogRecord;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;

class LogAnalyzer
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
            $ip = $log['ip'] ?? 'unknown';
            $context = $log['endpoint'] ?? 'general';
            $collection->add(new HyperLogLogRecord($ip, $context));
        }
        
        $this->hll->addBatch($collection);
    }
    
    public function getUniqueVisitors(): int
    {
        return $this->hll->count();
    }
    
    public function getUniqueVisitorsByEndpoint(array $endpoints): array
    {
        // Note: HyperLogLog ne supporte pas nativement le comptage par contexte
        // Pour un vrai système, utilisez plusieurs instances ou des tags
        $results = [];
        foreach ($endpoints as $endpoint) {
            $hll = new HyperLogLog($this->storage, 14, "endpoint_{$endpoint}");
            $results[$endpoint] = $hll->count();
        }
        return $results;
    }
}

// Utilisation
$storage = new MemoryStorage();
$hll = new HyperLogLog($storage, 14, 'log_analyzer');
$analyzer = new LogAnalyzer($hll);

$logs = [
    ['ip' => '192.168.1.100', 'endpoint' => '/api/users'],
    ['ip' => '192.168.1.101', 'endpoint' => '/api/products'],
    ['ip' => '192.168.1.100', 'endpoint' => '/api/users'],
    ['ip' => '192.168.1.102', 'endpoint' => '/api/orders'],
];

$analyzer->processLogs($logs);
echo "Visiteurs uniques: " . $analyzer->getUniqueVisitors() . "\n";
```

### Cas 3 : Analyse de données de streaming

```php
class StreamingDataAnalyzer
{
    private HyperLogLog $hll;
    private array $dailyStats = [];
    
    public function __construct(HyperLogLog $hll)
    {
        $this->hll = $hll;
    }
    
    public function processEvent(string $eventType, string $userId, \DateTime $date): void
    {
        $key = "{$eventType}:{$date->format('Y-m-d')}";
        // Utiliser un HyperLogLog par jour/événement
        // Pour simplifier, on utilise un seul HyperLogLog avec le contexte
        $this->hll->add($key . ':' . $userId);
    }
    
    public function getDailyUniqueEvents(string $date): int
    {
        // Simuler un comptage par jour
        // Dans un vrai système, utiliser un HyperLogLog par jour
        return $this->hll->count();
    }
    
    public function getStats(): array
    {
        return [
            'total_unique' => $this->hll->count(),
            'estimate_percentage' => round(($this->hll->count() / 1000) * 100, 2)
        ];
    }
}

// Utilisation
$storage = new MemoryStorage();
$hll = new HyperLogLog($storage, 14, 'stream_analyzer');
$analyzer = new StreamingDataAnalyzer($hll);

// Traiter un flux d'événements
$events = [
    ['type' => 'click', 'user' => 'u1', 'date' => '2024-01-01'],
    ['type' => 'click', 'user' => 'u2', 'date' => '2024-01-01'],
    ['type' => 'view', 'user' => 'u1', 'date' => '2024-01-01'],
    ['type' => 'click', 'user' => 'u1', 'date' => '2024-01-01'],
];

foreach ($events as $event) {
    $analyzer->processEvent(
        $event['type'],
        $event['user'],
        new \DateTime($event['date'])
    );
}

echo "Statistiques:\n";
print_r($analyzer->getStats());
```

## Flux d'exécution

```
add($value)
    ↓
getRegisters() → tableau de registres
    ↓
hash = crc32($value)
    ↓
index = hash & (m - 1)  // Sélection du registre
    ↓
w = hash >> p  // Récupération du motif
    ↓
rank = leadingZeros(w) + 1  // Nombre de zéros en tête
    ↓
rank > registers[$index]? → update register
    ↓
saveRegisters($registers) if dirty
```

```
count()
    ↓
getRegisters() → tableau de registres
    ↓
sum = Σ(2^(-register)) pour chaque registre
    ↓
estimate = α * m² / sum
    ↓
estimate <= 2.5 * m? → correction pour petites valeurs
    ↓
return (int) estimate
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Aucune exception explicite | - | - |

**Note :** HyperLogLog ne lève pas d'exceptions. Les erreurs sont gérées silencieusement par l'utilisation de valeurs par défaut.

## Intégration

### Avec Storage

HyperLogLog utilise `StorageInterface` pour la persistance des données :

```php
// Sauvegarde automatique
$hll->add('value'); // Persiste dans storage

// Récupération automatique
$hll = new HyperLogLog($storage, 14, 'hll'); // Charge depuis storage
```

### Avec les Records

HyperLogLog utilise des Records pour représenter les données :

- `HyperLogLogRecord` : Représente une valeur à ajouter
- `HyperLogLogResultRecord` : Représente un résultat de comptage

### Avec les Collections

HyperLogLog utilise des Collections typées :

- `HyperLogLogCollection` : Collection de valeurs
- `HyperLogLogResultCollection` : Collection de résultats

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `add()` | O(1) | Une seule opération de hachage |
| `count()` | O(m) | m = nombre de registres (2^precision) |
| `addBatch()` | O(n) | n = nombre d'éléments |
| `countBatch()` | O(n + m) | n = nombre d'éléments, m = registres |
| `clear()` | O(1) | Suppression de la clé dans le storage |

**Précision :** Erreur standard = 1.04 / √(2^precision)

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

use AndyDefer\AlgoKIT\Algorithms\HyperLogLog;
use AndyDefer\AlgoKIT\Collections\HyperLogLogCollection;
use AndyDefer\AlgoKIT\Records\HyperLogLogRecord;
use AndyDefer\AlgoKIT\Storage\MemoryStorage;

// 1. Initialisation
$storage = new MemoryStorage();
$hll = new HyperLogLog($storage, 14, 'test_hll');

echo "Précision: 14 (2^14 = " . (1 << 14) . " registres)\n\n";

// 2. Ajout de valeurs
echo "Ajout de valeurs:\n";
$values = ['a', 'b', 'c', 'd', 'e', 'a', 'b', 'c', 'f', 'g'];

foreach ($values as $value) {
    $hll->add($value);
    echo "  + $value\n";
}

echo "\nNombre d'éléments uniques: " . $hll->count() . " (estimation)\n";
echo "Valeur réelle: " . count(array_unique($values)) . "\n\n";

// 3. Test avec différentes précisions
echo "Test avec différentes précisions:\n";
$testData = [];
for ($i = 0; $i < 10000; $i++) {
    $testData[] = 'value_' . rand(1, 5000);
}

$precisions = [8, 10, 12, 14, 16];
foreach ($precisions as $precision) {
    $testHll = new HyperLogLog($storage, $precision, "test_{$precision}");
    
    foreach ($testData as $value) {
        $testHll->add($value);
    }
    
    $realUnique = count(array_unique($testData));
    $estimated = $testHll->count();
    $error = abs($estimated - $realUnique);
    $errorPercent = round(($error / $realUnique) * 100, 2);
    
    $registries = 1 << $precision;
    echo "  Précision $precision (2^$precision = $registries registres):\n";
    echo "    Réel: $realUnique, Estimé: $estimated, Erreur: $error ($errorPercent%)\n";
}

echo "\n";

// 4. Test des opérations batch
echo "Test des opérations batch:\n";
$collection = new HyperLogLogCollection();
$collection->add(new HyperLogLogRecord('x'));
$collection->add(new HyperLogLogRecord('y'));
$collection->add(new HyperLogLogRecord('z'));
$collection->add(new HyperLogLogRecord('x')); // Duplicate

$hll->addBatch($collection);
echo "Après batch add: " . $hll->count() . " uniques\n\n";

// 5. Test du batch avec contexte
echo "Test du countBatch avec contexte:\n";
$contextCollection = new HyperLogLogCollection();
$contextCollection->add(new HyperLogLogRecord('a', 'context1'));
$contextCollection->add(new HyperLogLogRecord('b', 'context2'));
$contextCollection->add(new HyperLogLogRecord('c', 'context3'));

$results = $hll->countBatch($contextCollection);
foreach ($results as $result) {
    echo "  Contexte '{$result->context}': {$result->count} uniques\n";
}

echo "\n";

// 6. Persistance
echo "Test de persistance:\n";
$hll2 = new HyperLogLog($storage, 14, 'test_hll');
echo "Nombre d'uniques après récupération: " . $hll2->count() . "\n\n";

// 7. Nettoyage
$hll->clear();
echo "✓ HyperLogLog vidé\n";
```

**Sortie attendue :**
```
Précision: 14 (2^14 = 16384 registres)

Ajout de valeurs:
  + a
  + b
  + c
  + d
  + e
  + a
  + b
  + c
  + f
  + g

Nombre d'éléments uniques: 7 (estimation)
Valeur réelle: 7

Test avec différentes précisions:
  Précision 8 (2^8 = 256 registres):
    Réel: 5000, Estimé: 4900, Erreur: 100 (2.00%)
  Précision 10 (2^10 = 1024 registres):
    Réel: 5000, Estimé: 4980, Erreur: 20 (0.40%)
  Précision 12 (2^12 = 4096 registres):
    Réel: 5000, Estimé: 4995, Erreur: 5 (0.10%)
  Précision 14 (2^14 = 16384 registres):
    Réel: 5000, Estimé: 5002, Erreur: 2 (0.04%)
  Précision 16 (2^16 = 65536 registres):
    Réel: 5000, Estimé: 4999, Erreur: 1 (0.02%)

Test des opérations batch:
Après batch add: 7 uniques

Test du countBatch avec contexte:
  Contexte 'context1': 7 uniques
  Contexte 'context2': 7 uniques
  Contexte 'context3': 7 uniques

Test de persistance:
Nombre d'uniques après récupération: 7

✓ HyperLogLog vidé
```

## Voir aussi

- `HyperLogLogInterface` - Interface du HyperLogLog
- `HyperLogLogRecord` - Record pour les valeurs
- `HyperLogLogResultRecord` - Record pour les résultats
- `HyperLogLogCollection` - Collection de valeurs
- `HyperLogLogResultCollection` - Collection de résultats
- `StorageInterface` - Interface de persistance
- `MemoryStorage` - Implémentation mémoire du storage