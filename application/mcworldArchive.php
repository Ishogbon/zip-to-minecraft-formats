<?php

class MinecraftFileArchiver {
    const MAX_FILES_IN_ARCHIVE = 150;
    const MAX_ARCHIVE_SIZE = 1024 * 1024 * 100; // 100 MB
    const PROGRAM_TYPE = 'application';
    function __construct()
    {
    }

    function handleZipError($errorCode) {
        $error_type = 'ERROR';
        $error_MESSAGE = '';
        echo $errorCode, ZipArchive::ER_NOZIP ;
        switch ($errorCode) {
            case ZIPARCHIVE::ER_NOZIP:
                $error_MESSAGE = "Not a valid ZIP archive.";
            case ZIPARCHIVE::ER_INCONS:
                $error_MESSAGE = "Zip archive inconsistent.";
            case ZIPARCHIVE::ER_CRC:
                $error_MESSAGE = "CRC error.";
            case ZIPARCHIVE::ER_MEMORY:
                $error_MESSAGE = "Memory allocation error.";
            case ZIPARCHIVE::ER_READ:
                $error_MESSAGE = "Read error.";
            case ZIPARCHIVE::ER_SEEK:
                $error_MESSAGE = "Seek error.";
            case ZIPARCHIVE::ER_WRITE:
                $error_MESSAGE = "Write error.";
            case ZIPARCHIVE::ER_EXISTS:
                $error_MESSAGE = "File already exists.";
            case ZIPARCHIVE::ER_OPEN:
                $error_MESSAGE = "Failed to open the file.";
            case ZIPARCHIVE::ER_ZLIB:
                $error_MESSAGE = "Error with compression format";
            case ZIPARCHIVE::ER_OPEN:
                $error_MESSAGE = "Failed to open the file.";
            default:
                $error_MESSAGE = "A compression error has occured.";
        }
        return ["TYPE" => $error_type, "MESSAGE" => $error_MESSAGE];
    }
    
    public function secureZipUnarchiver($directory_to_unzip_to, $zip_file_path) {
        if(filesize($zip_file_path) > $this::MAX_ARCHIVE_SIZE) {
            throw new Exception("File size larger than maximum");
        } else if (preg_match(@'/[^\w\-. ]/', basename($zip_file_path))) {
            throw new Exception("Invalid archive name detected");
        }
        $zip = new ZipArchive();
        try {
            // Open the ZIP file
            if ($zip->open($zip_file_path) === true) {
                // Create a temporary directory to extract the contents
                mkdir($directory_to_unzip_to, 0666, true);
                $totalFilesSize = 0;
                // Limit the amount of files in Archive to prevent infinite recursion
                if ($zip->numFiles > $this::MAX_FILES_IN_ARCHIVE) {
                    throw new Exception(("Number of files in archive has been exceeded"));
                }
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $file_info = $zip->statIndex($i);
                    $totalFilesSize += $file_info['size'];
                    // Check for potential path traversal
                    if (preg_match(@'/(\.\.\/)|(\.\.\\\)/', $file_info['name'])) {
                        throw new Exception("Invalid file path detected");
                    }
                }
                if ($totalFilesSize > $this::MAX_ARCHIVE_SIZE) {
                    throw new Exception("The archive contents exceeds the maximum allowed size.");
                }
                // Extract once archive has been validated
                $zip->extractTo($directory_to_unzip_to);
                $zip->close();
                return true;
            }
            else {
                return $this->handleZipError($zip->status);
            }
        } catch(Exception $err) {
            return ["TYPE" => "ERROR", "MESSAGE" => $err->getMessage()];
        }
        
    }

    function compressToMinecratFormat($contents_directory, $output_file_path) {
        try {
            // Create a new ZIP archive for the MCWorld format
            $mc_zip = new ZipArchive();
            // Open the MCWorld ZIP archive
            if ($mc_zip->open($output_file_path, ZipArchive::CREATE) === true) {
                // Add the extracted files and directories to the MCWorld archive
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($contents_directory.'/'));
                foreach ($iterator as $file) {
                    $file_path = $file->getRealPath();
                    // Determine the relative path of the file within the temporary directory
                    $relative_path = substr($file_path, strlen($contents_directory) + 1);
                    
                    if (is_dir($file_path)) {
                        // Add directories to the MCWorld archive
                        $mc_zip->addEmptyDir($relative_path);
                    } else {
                        // Add files to the MCWorld archive
                        $mc_zip->addFile($file_path, $relative_path);
                    }
                }
                
                // Close the MCWorld archive
                $mc_zip->close();
                
                // Remove the temporary directory
                $this->remove_dir($contents_directory);
                
                return ["TYPE" => "SUCCESS", "MESSAGE" => $output_file_path];
            } else {
                // Failed to open the MCWorld ZIP archive
                $this->remove_dir($contents_directory);
                return ["TYPE" => "ERROR", "MESSAGE" => "Error occured compressing to desired format"];
            }
        } catch (Exception $err) {
            return ["TYPE" => "ERROR", "MESSAGE" => $err->getMessage()];
        }
    }

    function zip_inter_compression($zip_file_path, $destination_file_name, $extention) {
        if(empty($destination_file_name)) {
            $destination_file_name = pathinfo(basename($zip_file_path), PATHINFO_FILENAME);
        }
        $tempDir = dirname($zip_file_path) . '/'.$extention.'_conversion_' . uniqid();
        $uncompress_resp = $this->secureZipUnarchiver($tempDir.'/zip_contents', $zip_file_path);
        if ($uncompress_resp === true) {
            return $this->compressToMinecratFormat($tempDir.'/zip_contents', $tempDir.'/'.$destination_file_name.'.'.$extention);
        }
        return $uncompress_resp;
    }

    function zip_to_mcworld($zip_file_path, $destination_file_name) {
        return $this->zip_inter_compression($zip_file_path, $destination_file_name, 'mcworld');   
    }

    function zip_to_mcpack($zip_file_path, $destination_file_name) {
        return $this->zip_inter_compression($zip_file_path, $destination_file_name, 'mcpack');   
    }

    function zip_to_mcaddon($zip_file_path, $destination_file_name) {
        return $this->zip_inter_compression($zip_file_path, $destination_file_name, 'mcaddon');   
    }

    function zip_to_mctemplate($zip_file_path, $destination_file_name) {
        return $this->zip_inter_compression($zip_file_path, $destination_file_name, 'mctemplate');   
    }

    function mc_to_zip($mf_file_path, $destination_file_name) {
        return $this->zip_inter_compression($mf_file_path, $destination_file_name, 'zip');   
    }

    
    // Helper function to remove a directory and its contents recursively
    function remove_dir($dir) {
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->remove_dir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}

?>