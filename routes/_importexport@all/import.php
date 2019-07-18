<?php
$package->noCache();

$form = $cms->helper('forms')->form('');
$n = $cms->helper('notifications');

$form['file'] = $cms->helper('forms')->field('Formward\\Fields\\File', 'Digraph export file');
$form['file']->attr('accept', '.json');
$form['file']->required();

echo $form;

//try to set max execution time to unlimited
ini_set('max_execution_time', 0);

if ($form->handle()) {
    $limit = intval(ini_get('max_execution_time'));
    $limit = $limit?$limit-2:0;
    $start = time();
    if (!($data = file_get_contents($form['file']->value()['file']))) {
        $n->error('Error reading uploaded file. Server may be misconfigured.');
        return;
    }
    if (!($data = json_decode($data, true))) {
        $n->error('Error unserializing file.');
        return;
    }
    //successfully read file
    $log = [];
    $log[] = 'Expecting to import '.intval($data['digraph_export']['results']).' nouns';
    //do initial content import
    $inserted = 0;
    $merged = 0;
    $nouns = [];
    foreach ($data['nouns'] as $in) {
        if ($limit && time()-$start >= $limit) {
            $log[] = 'Ran out of time';
            $errors++;
            break;
        }
        $id = $in['dso']['id'];
        $new = false;
        if (!($nouns[$id] = $cms->factory()->read($id, 'dso.id', null))) {
            $nouns[$id] = $cms->factory()->create($in);
            if ($nouns[$id]->insert()) {
                $log[] = 'inserted '.$nouns[$id]->name().' ('.$id.')';
                $inserted++;
            } else {
                $log[] = 'error inserting '.$nouns[$id]->name().' ('.$id.')';
                $errors++;
            }
        } else {
            if ($in['dso']['created']['date'] < $nouns[$id]['dso.created.date']) {
                unset($in['dso']['created']);
            }
            if ($in['dso']['modified']['date'] < $nouns[$id]['dso.modified.date']) {
                unset($in['dso']['modified']);
            }
            $nouns[$id]->merge($in, null, true);
            if ($nouns[$id]->update(true)) {
                $log[] = 'merged '.$nouns[$id]->name().' ('.$id.')';
                $merged++;
            } else {
                $log[] = 'error merging '.$nouns[$id]->name().' ('.$id.')';
                $errors++;
            }
        }
    }
    //confirmation message
    if ($added || $merged) {
        $n->confirmation("Added $inserted new nouns, merged $merged.");
    }
    //see if helpers have hook_import method, and call it if they do
    foreach ($cms->allHelpers() as $name) {
        if ($limit && time()-$start >= $limit) {
            $log[] = 'Ran out of time';
            $errors++;
            break;
        }
        if (method_exists($cms->helper($name), 'hook_import')) {
            $log[] = 'Import hook from '.$name;
            if ($r = $cms->helper($name)->hook_import($data, $nouns)) {
                if (is_array($r)) {
                    foreach ($r as $v) {
                        $log[] = $v;
                    }
                } else {
                    $log[] = $r;
                }
            }
        }
    }
    //count errors and warnings
    $errors = 0;
    $warnings = 0;
    foreach ($log as $l) {
        if (substr($l, 0, 6) == 'ERROR:') {
            $errors++;
        }
        if (substr($l, 0, 8) == 'WARNING:') {
            $warnings++;
        }
    }
    if ($errors) {
        $n->error("Import encountered $errors errors. See log for more information.");
    }
    if ($warnings) {
        $n->error("Import encountered $warnings warnings. See log for more information.");
    }
    //display log
    echo "<pre>".implode(PHP_EOL, $log)."</pre>";
}
