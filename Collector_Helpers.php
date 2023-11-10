<?php
function collector_get_tmpfile($name, $type)
{
    $tmpName = tempnam('/tmp/', 'clctr-'. date('Y-m-d_H-i-s-') . $name . '-');
    $typName = $tmpName . '.' . $type;
    unlink($tmpName);

    return $typName;
}
