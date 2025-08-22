<?php
function getAvatarPath($avatar, $gender = '')
{
    if (!empty($avatar) && file_exists('uploads/avatars/' . $avatar)) {
        return 'uploads/avatars/' . $avatar;
    }

    $gender = strtolower($gender);
    if ($gender === 'female') {
        return 'img/FemDef.png';
    } else {
        return 'img/MaleDef.png';
    }
}

function getProductImagePath($imageName)
{
    $path = 'uploads/products/' . $imageName;
    if (!empty($imageName) && file_exists($path)) {
        return $path;
    }

    return 'img/ProDef.png'; 
}


?>