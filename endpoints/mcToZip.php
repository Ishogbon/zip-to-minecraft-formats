<?php

const GENERIC_NO_FILE_WAS_PRESENT = ["TYPE" => "ERROR", "MESSAGE" => "No file present"];

if ($_FILES["archive"]["tmp_name"] && $_FILES["archive"]["name"]) {
    require('../application/mcworldArchive.php');
    $zip_archiver = new MinecraftFileArchiver();
    echo json_encode($zip_archiver->mc_to_zip($_FILES["archive"]["tmp_name"], pathinfo($_FILES["archive"]["name"], PATHINFO_FILENAME)));
} else {
    echo json_encode(GENERIC_NO_FILE_WAS_PRESENT);
}
?>