<?php
/**
 * Created by PhpStorm.
 * User: acarrasco
 * Date: 8/23/2016
 * Time: 2:50 PM
 */?>

<textarea rows="4" cols="30" name="BookList-extra-listDescription"
          placeholder="Please insert a description for this list"><?php
    if ($this->_extra != null) {
        echo isset($this->_extra['listDescription']) ? $this->_extra['listDescription'] : '';
    }
    ?></textarea>

<textarea rows="4" cols="30" name="BookList-extra-isbn"
          placeholder="Please insert comma-separated ISBN numbers"><?php
    if ($this->_extra != null) {
        echo $this->_extra['isbn'];
    }
    ?></textarea>

<script>
    var b = bookList();
    b.initEditView();
</script>