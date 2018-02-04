<?php

/**
 * TODO:
 * - zpracovat metadata
 * - metadata musi souhlasit s IO mappingem
 *
 */
require_once 'vendor/autoload.php';

use GraphAware\Neo4j\Client\ClientBuilder;
// https://github.com/graphaware/neo4j-php-client
use Symfony\Component\Process\Process;
use Symfony\Component\Stopwatch\Stopwatch;

$client = new \Keboola\StorageApi\Client(
    [
        'token' => '578-some-token',
        'url' => 'https://connection.keboola.com/'
    ]
);

$data = [];

//goto neo4;
//exit;

$stopwatch = new Stopwatch(true);
$stopwatch->start('gettables');
$tables = $client->listTables();
foreach ($tables as $table) {
    // CREATE (TaMysqlCars:Table {title: 'cars', id: 'in.c-ex-mysql.cars'})
    $id = $table['id'];
    $name = $table['name'];
    $nodeId = strtr($id, '-? @#$%^&*().:,;', '________________');
    $data[] = "CREATE ($nodeId:Table {title: '$name', id: '$id'})";
}
$gettables = $stopwatch->stop('gettables');

$stopwatch = new Stopwatch(true);
$stopwatch->openSection();
$stopwatch->start('getconfigs');
$componentClient = new \Keboola\StorageApi\Components($client);
$opt = new \Keboola\StorageApi\Options\Components\ListComponentsOptions();
$opt->setInclude(['configuration', 'rows']);
$components = $componentClient->listComponents($opt);
foreach ($components as $component) {
    //$opt = new \Keboola\StorageApi\Options\Components\ListComponentConfigurationsOptions();
    //$opt->setComponentId($component['id']);
    //$configs = $componentClient->listComponentConfigurations($opt);
    $type = $component['type'];
    foreach ($component['configurations'] as $config) {
        $id = $config['id'];
        $name = $config['name'];
        $nodeId = strtr('cfg' . $component['id'] . $id, '-? @#$%^&*().:,;', '________________');
        switch ($type) {
            case 'extractor': $nodeType = 'Source'; break;
            case 'writer': $nodeType = 'Target'; break;
            default: $nodeType = 'Configuration';
        }
        if ($component['id'] == 'transformation') {
            $nodeType = 'Transformation';
        }

        $data[] = "CREATE ($nodeId:$nodeType {title: '$name', id: '$id'})";

        if (!empty($config['configuration']['storage']['input']['tables'])) {
            foreach ($config['configuration']['storage']['input']['tables'] as $table) {
                $source = $table['source'];
                $source = strtr($source, '-? @#$%^&*().:,;', '________________');

                $data[] = "CREATE ($source)-[:READBY]->($nodeId)";

            }
            // CREATE (Tr1:Configuration {title: 'First Transformation', id: 'tr-first'})
            // CREATE (TaMysqlCars:Table {title: 'cars', id: 'in.c-ex-mysql.cars'})
            // CREATE (Tr1)-[:WRITES]->(TaOutTable3)
            //echo "Some input mapping for component " . $config['componentId'];
        }
        if (!empty($config['configuration']['storage']['output']['tables'])) {
            foreach ($config['configuration']['storage']['output']['tables'] as $table) {
                $source = $table['destination'];
                $source = strtr($source, '-? @#$%^&*().:,;', '________________');

                $data[] = "CREATE ($nodeId)-[:WRITES]->($source)";

            }
            //echo "Some output mapping for component " . $config['componentId'];
        }
        if (!empty($config['configuration']['parameters']['tables'])) {
            // db extractors
            foreach ($config['configuration']['parameters']['tables'] as $table) {
                if (!empty($table['outputTable'])) {
                    $source = $table['outputTable'];
                } elseif (!empty($table['tableId'])) {
                    // legacy format?
                    $source = $table['tableId'];
                } elseif (!empty($table['outputTable'])) {
                    // tdeexporter? -> should be considered as input
                    //$source = $table['outputTable']; -> has storage.input.node
                } else {
                    //throw new Exception("?");
                }
                $source = strtr($source, '-? @#$%^&*().:,;', '________________');

                $data[] = "CREATE ($nodeId)-[:WRITES]->($source)";
            }
        }
        if (!empty($config['rows'])) {
            foreach ($config['rows'] as $row) {
                if (!empty($row['configuration']['input'])) {
                    // legacy transformation format
                    foreach ($row['configuration']['input'] as $table) {
                        $source = $table['source'];
                        $source = strtr($source, '-? @#$%^&*().:,;', '________________');

                        $data[] = "CREATE ($source)-[:READBY]->($nodeId)";
                    }
                }
                if (!empty($row['configuration']['output'])) {
                    // legacy transformation format
                    foreach ($row['configuration']['output'] as $table) {
                        $source = $table['destination'];
                        $source = strtr($source, '-? @#$%^&*().:,;', '________________');

                        $data[] = "CREATE ($nodeId)-[:WRITES]->($source)";
                    }
                }

                if (!empty($row['configuration']['storage']['input']['tables'])) {
                    foreach ($row['configuration']['storage']['input']['tables'] as $table) {
                        $source = $table['source'];
                        $source = strtr($source, '-? @#$%^&*().:,;', '________________');

                        $data[] = "CREATE ($source)-[:READBY]->($nodeId)";

                    }
                }
                if (!empty($row['configuration']['storage']['output']['tables'])) {
                    foreach ($row['configuration']['storage']['output']['tables'] as $table) {
                        $source = $table['destination'];
                        $source = strtr($source, '-? @#$%^&*().:,;', '________________');

                        $data[] = "CREATE ($nodeId)-[:WRITES]->($source)";

                    }
                }
            }
        }
    }
}
$getconfigs = $stopwatch->stop('getconfigs');
file_put_contents("./data/data.dump2", implode("\n", $data));


/*
echo "starting server\n\n";
$process = new Process('cd /var/lib/neo4j && /docker-entrypoint.sh neo4j');
// /var/lib/neo4j
$process->start(function ($type, $buffer) {
    if (Process::ERR === $type) {
        echo 'ERR > '.$buffer;
    } else {
        echo 'OUT > '.$buffer;
    }
});

echo  "connecting\n\n";
$try = 0;
while (true) {
    try {
        $try++;
        echo "try$try\n\n";
        $client = ClientBuilder::create()
            //    ->addConnection('default', 'http://neo4j:password@127.0.0.1:7474')
            // // Example for HTTP connection configuration (port is optional)
            ->addConnection('bolt', 'bolt://neo4j:neo4j@127.0.0.1:7687')
            // Example for BOLT connection configuration (port is optional)
            ->build();
        $client->run("MATCH (n) RETURN n;");
        break;
        //$client = ClientBuilder::create()
        //    ->addConnection('bolt', 'bolt://neo4j:neo4j@localhost:7687')
        //    ->build();
    } catch (\Exception $e) {
        echo "connection failed: " . $e->getMessage();
        sleep(5);
        if ($try > 10) {
            throw $e;
        }
    }
}
*/

neo4:

$client = ClientBuilder::create()->addConnection('default', 'bolt://neo4j:neo4j@127.0.0.1:17687')->build();

$stopwatch = new Stopwatch(true);
$stopwatch->start('cleardb');
echo "clearing db\n\n";
$result = $client->run("MATCH (n) OPTIONAL MATCH (n)-[r]-() WITH n,r LIMIT 50000 DELETE n,r RETURN count(n) as deletedNodesCount");
echo "res: " . $result->records()[0]->value('deletedNodesCount');
$clearDb = $stopwatch->stop('cleardb');

echo "importing data\n\n";
$stopwatch = new Stopwatch(true);
$stopwatch->start('importdb');
$data = file_get_contents('./data/data.dump2');
$result = $client->run($data);
$importDb = $stopwatch->stop('importdb');

function d3graph(\GraphAware\Common\Result\Result $result)
{
    /*
    $nodes = $edges = $identityMap = [];
    foreach ($result->records() as $record) {
        $source = $record->get('source');
        $destination = $record->get('destination');
        $actedRelationship = $record->get('r');
        $nodes[] = [
            'title' => $source->value('title'),
            'id' => $source->value('id'),
            'label' => $source->labels()[0]
        ];
        $identityMap[$source->identity()] = count($nodes)-1;
        $nodes[] = [
            'title' => $destination->value('title'),
            'id' => $source->value('id'),
            'label' => $destination->labels()[0]
        ];
        $identityMap[$destination->identity()] = count($nodes)-1;
        $edges[] = [
            'source' => $identityMap[$actedRelationship->startNodeIdentity()],
            'target' => $identityMap[$actedRelationship->endNodeIdentity()]
        ];
    }*/

    $nodes = $edges = $identityMap = [];
    foreach ($result->records() as $record) {
        try {
            $source = $record->get('Source');
            $destination = $record->get('Destination');
            // $actedRelationship = $record->get('r');
            $nodes[$source->value('id')] = [
                'title' => $source->value('title'),
                'id' => $source->value('id'),
                'label' => $source->labels()[0]
            ];
            // $identityMap[$source->identity()] = count($nodes)-1;
            $nodes[$destination->value('id')] = [
                'title' => $destination->value('title'),
                'id' => $destination->value('id'),
                'label' => $destination->labels()[0]
            ];
            // $identityMap[$destination->identity()] = count($nodes)-1;
            $edges[] = [
                'source' => $source->value('id'),
                'target' => $destination->value('id')
            ];
        } catch (\Exception $e) {
            //echo "found nonexistent table in " . var_export($record, true);
            $source = $record->get('Source');
            $destination = $record->get('Destination');
            if ($destination->hasValue('id')) {
                $nodes[$destination->value('id')] = [
                    'title' => $destination->value('title'),
                    'id' => $destination->value('id'),
                    'label' => $destination->labels()[0]
                ];
                $id = uniqid('invalid_source');
                $nodes[$id] = [
                    'title' => 'non-existent',
                    'id' => $id,
                    'label' => 'Missing'
                ];
            } else {
                $nodes[$source->value('id')] = [
                    'title' => $source->value('title'),
                    'id' => $source->value('id'),
                    'label' => $source->labels()[0]
                ];
                $id = uniqid('invalid_destination');
                $nodes[$id] = [
                    'title' => 'non-existent',
                    'id' => $id,
                    'label' => 'Missing'
                ];
            }
            //throw $e;
        }
    }

    return [
        'nodes' => array_values($nodes),
        'links' => $edges
    ];
}

function d3graphPath(\GraphAware\Common\Result\Result $result)
{
    $nodes = $edges = $identityMap = [];
    $nodeIndex = [];
    foreach ($result->records() as $record) {
        try {
            $rnodes = $record->get('nodes');
            /** @var \GraphAware\Bolt\Result\Type\Node $node */
            foreach ($rnodes as $node) {
                if ($node->hasValue('id')) {
                    $nodes[$node->value('id')] = [
                        'title' => $node->value('title'),
                        'id' => $node->value('id'),
                        'label' => $node->labels()[0]
                    ];
                    $nodeIndex[$node->identity()] = $node->value('id');
                } else {
                    // invalid node
                }
            }
            $rels = $record->get('rels');
            /** @var \GraphAware\Bolt\Result\Type\Relationship $relationship */
            foreach ($rels as $relationship) {
                if (isset($nodeIndex[$relationship->startNodeIdentity()]) && isset($nodeIndex[$relationship->endNodeIdentity()])) {
                    $edges[] = [
                        'source' => $nodeIndex[$relationship->startNodeIdentity()],
                        'target' => $nodeIndex[$relationship->endNodeIdentity()]
                    ];
                } else {
                    /// todo pridat invalid node a vypsat nejak?
                }
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }
    return [
        'nodes' => array_values($nodes),
        'links' => $edges
    ];
}


// complete graph
echo "executing query\n\n";
$stopwatch = new Stopwatch(true);
$stopwatch->start('completegraph');
$result = $client->run('MATCH (Source)-[r]-(Destination) RETURN r,Source,Destination;');
$data = d3graph($result);
$dataJson = json_encode($data, JSON_PRETTY_PRINT);
$completeGraph = $stopwatch->stop('completegraph');
file_put_contents('./data/graphData1.js', $dataJson);


/*
echo "executing json query\n\n";
$c = new \GuzzleHttp\Client();
$body = json_encode(
    [
        "statements" => [
            [
                "statement"  => "MATCH p = (n)-[r]-(m) RETURN p",
                "resultDataContents" => ["graph"]
            ]
        ]
    ]
);
$r = new \GuzzleHttp\Psr7\Request('POST', 'http://localhost:7474/db/data/transaction/commit',
    ['Accept' => 'application/json; charset=UTF-8'], $body);
$resp = $c->send($r);
file_put_contents('./data/graphData.js2', json_encode(json_decode($resp->getBody()), JSON_PRETTY_PRINT));
*/

$nodeId = '352746870';
// jen veci, ktere pouzivaji neco vytvoreneho v konfiguraci $nodeId
echo "executing query\n\n";
$stopwatch = new Stopwatch(true);
$stopwatch->start('querynode');
// http://neo4j.com/docs/developer-manual/current/cypher/functions/
$result = $client->run('MATCH p = (Source:Source)-[*]->(Destination:Target) WHERE Source.id = \'' . $nodeId . '\' RETURN  relationships(p) AS rels, nodes(p) AS nodes;');
$data = d3graphPath($result);
$dataJson = json_encode($data, JSON_PRETTY_PRINT);
$queryNode = $stopwatch->stop('querynode');
file_put_contents('./data/graphData2.js', $dataJson);

// jen veci, ktere tvori uplnou cestu mezi source a destination
echo "executing query\n\n";
$stopwatch = new Stopwatch(true);
$stopwatch->start('querypath');
// -> nefektivni
//$result = $client->run('MATCH (Source:Source)-[*]-(Middle)-[*]-(Destination:Target) RETURN Source,Middle,Destination;');
// -> neumim zpracovat pah?
//$result = $client->run('MATCH p = (Source:Source)-[*]->(Destination:Target) RETURN Source,Destination,p;');
$result = $client->run('MATCH p = (Source:Source)-[*]->(Destination:Target) RETURN relationships(p) AS rels, nodes(p) AS nodes;');
$data = d3graphPath($result);
$dataJson = json_encode($data, JSON_PRETTY_PRINT);
$queryPath = $stopwatch->stop('querypath');
file_put_contents('./data/graphData3.js', $dataJson);

// jen veci, ktere nepouzivaji vubec nic
echo "executing query\n\n";
$stopwatch = new Stopwatch(true);
$stopwatch->start('queryorphan');
$result = $client->run('MATCH p = (Source:Source)-[*]->(Destination:Target) UNWIND nodes(p) as nds WITH collect(nds) AS nodelist MATCH (n) WHERE NOT (n IN nodelist) RETURN n');

/**
 * potiz byl v tom, ze match vrati vsechny cesty mezi source a destination
(coz je logicky!), cili je to pole cest (timpadem kdyz se zeptam jestli
je v nem nejaka node, tak nikdy neni), takze potrebuju unwind nodes,
abych dostal jen vrcholy a pak zase collect abych ztoho udelal list a
mohl se na nej zeptat pres IN
 */
//$data = d3graph($result);
//$dataJson = json_encode($data, JSON_PRETTY_PRINT);
$nodes = [];
$invalidReferences  = [];
foreach ($result->records() as $record) {
    $source = $record->get('n');
    try {
        $nodes[$source->value('id')] = [
            'title' => $source->value('title'),
            'id' => $source->value('id'),
            'label' => $source->labels()[0]
        ];
    } catch (\Exception $e) {
       // echo "Found nonexistent node in " . var_export($source, true);
        $invalidReferences[] = $record;
    }
}
file_put_contents('./data/unused-nodes.js', json_encode($nodes, JSON_PRETTY_PRINT));
$queryOrphan = $stopwatch->stop('queryorphan');
echo "\ngettables: " . $gettables->getDuration() . "ms\n";
echo "getconfigs: " . $getconfigs->getDuration() . "ms\n";
echo "cleardb: " . $clearDb->getDuration() . "ms\n";
echo "importdb: " . $importDb->getDuration() . "ms\n";
echo "completegraph: " . $completeGraph->getDuration() . "ms\n";
echo "querypath: " . $queryPath->getDuration() . "ms\n";
echo "querynode: " . $queryNode->getDuration() . "ms\n";
echo "queryorphan: " . $queryOrphan->getDuration() . "ms\n";


exit;
echo "exiting\n\n";

echo "executing query\n\n";
$query = "MATCH p = (Source:Source)-[*]->(Destination:Target) UNWIND nodes(p) as nds 
WITH collect(nds) AS nodelist MATCH (n) WHERE NOT (n IN nodelist) RETURN n;";

$client->run('CREATE (n:Person) SET n += {infos}', ['infos' => ['name' => 'Ales', 'age' => 34]]);

$query = 'MATCH (n:Person) RETURN n, n.name as name, n.age as age';
$result = $client->run($query);

foreach ($result->records() as $record) {
    print_r($record->get('n')); // nodes returned are automatically hydrated to Node objects

    echo $record->value('name') . PHP_EOL;
    echo $record->value('age') . PHP_EOL;
}


echo "executing query2\n\n";
$query = "MATCH p = (Source:Source)-[*]->(Destination:Target) UNWIND nodes(p) as nds 
WITH collect(nds) AS nodelist MATCH (n) WHERE NOT (n IN nodelist) RETURN n.title AS title;";
/** @var \GraphAware\Common\Result\Result $result */
$result = $client->run($query);


// $client->run('CREATE (n:Person) SET n += {infos}', ['infos' => ['name' => 'Ales', 'age' => 34]]);

//foreach ($result->getRecords() as $record) {
//    echo sprintf('Person name is : %s and has %d number of friends', $record->value('name'), count($record->value('friends'));
//}

foreach ($result->records() as $record) {
    echo $record->value('title') . PHP_EOL;
    //var_export($record);
//    echo sprintf('number of unused nodes', count($record->value('title')));
}

// unused nodes

// nodes not used in X

// nodes used in X



