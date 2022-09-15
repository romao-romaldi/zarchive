<?php
/*
 * Copyright (C) 2015 Maarch
 *
 * This file is part of Laabs.
 *
 * Laabs is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Laabs is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with Laabs.  If not, see <http://www.gnu.org/licenses/>.
 */

// Require base script
require_once('../core/laabs.php');

laabs::init();

test();
function test() {

    $repoController = \laabs::newController('digitalResource/repository');
    $s3repository = $repoController->openRepository('maarchRM_ri95ij-hlcu-6bg2nh');
    var_dump($s3repository);
    $s3 = $s3repository->getService();
    var_dump($s3);

    $path = '<instance>/originatorOwnerOrgId/archivalProfileReference/<date(Y)>/<date(m)>/<date(d)>';

    $cont = $s3->createContainer($path);
    var_dump($cont);

    $path = $s3->createObject('data', $path.'/archiveId/resid');
    var_dump($path);

    $url = $s3->readObject($path);
    var_dump($url);

    $result = $s3->deleteObject($path);
    var_dump($result);
    exit;
}

switch (true) {
    // OpenAPI and other utils
    case (pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_FILENAME) == 'openapi'):
        switch (pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_EXTENSION)) {
            case 'json':
                require_once '../core/Openapi.php';
                $openapi = new core\Openapi();
                $body = json_encode($openapi, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES);
                header('Content-Type: application/json; charset=utf-8');
                header('Content-Length: '.strlen($body));
                echo $body;
                break;
        }
        break;

    // Uri is a static resource
    case (strrpos($_SERVER['SCRIPT_NAME'], ".")):
        \core\Kernel\StaticKernel::start();
        \core\Kernel\StaticKernel::run();
        \core\Kernel\StaticKernel::end();
        break;

    // Instance has a view
    case !\laabs::isServiceClient():
        \core\Kernel\PresentationKernel::start();
        \core\Kernel\PresentationKernel::run();
        \core\Kernel\PresentationKernel::end();
        break;

    // Instance is a service provider
    case \laabs::isServiceClient():
    default:
        \core\Kernel\ServiceKernel::start();
        \core\Kernel\ServiceKernel::run();
        \core\Kernel\ServiceKernel::end();
}
