<?php

require_once __DIR__ . '/vendor/autoload.php';

use ClickHouseDB\Client;

// ClickHouse bağlantısı
$ch = new Client([
    'host' => 'clickhouse',
    'port' => 8123,
    'username' => 'ismayil',
    'password' => '12345',
    'database' => 'testdb'
]);

// Kafka consumer ayarları
$conf = new RdKafka\Conf();
$conf->set('group.id', 'php-test-group');
$conf->set('metadata.broker.list', 'kafka:9092');

// Kafka consumer yarat
$consumer = new RdKafka\KafkaConsumer($conf);
$consumer->subscribe(['mysql.testdb.transactions']);

echo "Listener başladı, Kafka-dan mesaj gözləyir...\n";

while (true) {
    $message = $consumer->consume(10000); // 10 saniyə gözləmə
    switch ($message->err) {
        case RD_KAFKA_RESP_ERR_NO_ERROR:
            $payload = $message->payload;
            $data = json_decode($payload, true);

            // Debezium-un ExtractNewRecordState transformasiya ayarlı halda data bu formada olur:
            // {"id": 1, "Doc_Type": "purchase"}
            if (isset($data['id']) && isset($data['Doc_Type'])) {
                $id = intval($data['id']);
                $docType = $data['Doc_Type'];

                // ClickHouse insert - column-ları uyğunlaşdır
                try {
                    $result = $ch->insert('testdb.transactions', [
                        [$id, $docType]
                    ], ['id', 'Doc_Type']);

                    echo "Insert OK: id=$id, Doc_Type=$docType\n";
                } catch (Exception $e) {
                    echo "Insert error: ".$e->getMessage()."\n";
                }
            } else {
                echo "Kafka message: id və ya Doc_Type tapılmadı, data: $payload\n";
            }
            break;

        case RD_KAFKA_RESP_ERR__TIMED_OUT:
            // Yeni mesaj yoxdur, davam et
            break;

        default:
            echo "Kafka error: " . $message->errstr() . "\n";
            break;
    }
}
