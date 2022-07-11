<?php
/*
 * Copyright (C) 2020 Maarch
 *
 * This file is part of bundle organization.
 *
 * Bundle organization is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Bundle organization is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with bundle organization.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace bundle\medona\Connectors;

/**
 * @author Jerome Boucher <jerome.boucher@maarch.org>
 */
class Zip
{
    protected $messageDirectory;

    /**
     * Get archive transfer transformed by connector
     *
     * @param mixed $package The source of the message
     * @param array $params      Additional parameters
     *
     * @return array Array of medona/message object
     */
    public function receive($package, $params, $messageDirectory)
    {
        if (is_object($package) && (!isset($package->data) || empty($package->data))) {
            throw new \core\Exception\BadRequestException("Package data is mandatory", 400);
        }

        if (!is_dir($messageDirectory)) {
            throw new \core\Exception\BadRequestException("MessageDirectory is not a directory", 400);
        }
        $this->messageDirectory = $messageDirectory;

        if (is_object($package)) {
            $data = $package->data;
            $encoding = $package->encoding;
        } elseif (is_array($package)) {
            $data = $package['handler'];
            $encoding = null;
        } else if (is_string($package)) {
            $data = $package;
            $encoding = null;
        }


        $zipTmpFile = \laabs\tempnam();
        $this->receiveFile($data, $zipTmpFile, $encoding);

        $tmpDir = \laabs\tempdir();

        $zip = \laabs::newService('dependency/fileSystem/plugins/zip');
        $zip->extract($zipTmpFile, $tmpDir);

        $this->moveToMedona($tmpDir);

        $manifestName = 'manifest.xml';
        if (isset($params['manifest']) && !empty($params['manifest'])) {
            $manifestName = $params['manifest'];
        }

        if (!in_array($manifestName, scandir($messageDirectory))) {
            throw new \core\Exception\BadRequestException("The specified message file can not be found", 400);
        }

        return $this->messageDirectory . DIRECTORY_SEPARATOR . $manifestName;
    }

    protected function receiveFile($data, $zipTmpFile, $encoding = null)
    {
        if (!is_null($encoding) && $encoding == 'base64') {
            switch (true) {
                case is_resource($data):
                case is_string($data) &&
                    (
                        filter_var(substr($data, 0, 10), FILTER_VALIDATE_URL) ||
                        is_file($data)
                    ):
                    $handler = \core\Encoding\Base64::decode($data);
                    file_put_contents($zipTmpFile, stream_get_contents($handler));
                    break;
                case is_string($data):
                    file_put_contents($zipTmpFile, \core\Encoding\Base64::decode($data));

                    break;
                default:
                    throw new \core\Exception\BadRequestException("Data package format is not valid", 400);
                    break;
            }
        } else {
            switch (true) {
                case is_resource($data):
                    file_put_contents($zipTmpFile, stream_get_contents($data));
                    break;

                case is_string($data) &&
                    (
                        filter_var(substr($data, 0, 10), FILTER_VALIDATE_URL) ||
                        is_file($data)
                    ):
                    file_put_contents($zipTmpFile, file_get_contents($data));
                    break;
                case is_string($data):
                    file_put_contents($zipTmpFile, $data);
                    break;
                default:
                    throw new \core\Exception\BadRequestException("Data package format is not valid", 400);
                    break;
            }
        }
    }

    protected function moveToMedona($directory)
    {
        foreach (scandir($directory) as $file) {
            if ($file != "." && $file != "..") {
                if (is_link($directory.DIRECTORY_SEPARATOR.$file)) {
                    $this->sendError("202", "The container file contains symbolic links");
                    $exception = \laabs::newException('medona/invalidMessageException', "Invalid message", 400);
                    $exception->errors = $this->errors;

                    throw $exception;
                }

                rename($directory.DIRECTORY_SEPARATOR.$file, $this->messageDirectory.DIRECTORY_SEPARATOR.$file);
            }
        }
    }
}
