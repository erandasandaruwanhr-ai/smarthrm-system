<?php
header('Content-Type: application/json');

/**
 * SmartHRM Installation Cleanup
 * Handles post-installation security cleanup
 */

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !isset($data['action'])) {
        throw new Exception('Invalid request');
    }

    $response = ['success' => false, 'message' => ''];

    switch ($data['action']) {
        case 'delete_installer':
            $installer_dir = dirname(__DIR__);
            $deleted = deleteDirectory($installer_dir);

            if ($deleted) {
                $response = [
                    'success' => true,
                    'message' => 'Installer directory deleted successfully'
                ];
            } else {
                $response = [
                    'success' => false,
                    'message' => 'Could not delete installer directory. Please delete manually.'
                ];
            }
            break;

        default:
            throw new Exception('Unknown action');
    }

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Recursively delete a directory and its contents
 */
function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return false;
    }

    $files = array_diff(scandir($dir), ['.', '..']);

    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;

        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }

    return rmdir($dir);
}
?>