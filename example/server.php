<?php

include(__DIR__ . '../include.php');
$methods = [
    'demo.sayHello' => function (): string {

        return 'Hello World!';
    },

    'demo.substract' => function ($params) {

        [$num1, $num2] = $params;
        return $num1 - $num2;
    }
];
Tivoka\Server::provide($methods)->dispatch();
