<?php
/*
 * Bayes classifier
 * @author: Alouit Alex <alex@alouit.fr>
 */

$minimum_length = 3;
$useless_words = array(
  'le',
  'la',
  'et',
  'par',
  'pour',
  'ton',
  'ta',
  'vos',
  'votre',
  'il',
  'qui',
  'quoi',
  'un',
  'une'
);

// look for learn
foreach (array('spam', 'ham') as $type) {
  $director = new RecursiveDirectoryIterator(__DIR__ . "/learn/{$type}");
  $iterator = new RecursiveIteratorIterator($director, RecursiveIteratorIterator::SELF_FIRST);

  foreach ($iterator as $fileInfo) {
    if (! $fileInfo->isFile()) continue;

    $words = preg_split(
      '/\s+/',
      strtolower(
        file_get_contents(
          $fileInfo->getPathname()
        )
      )
    );

    $words = array_filter($words, function ($word) use ($minimum_length, $useless_words) {
      if (strlen($word) < $minimum_length)
        return false;

      if (in_array($word, $useless_words))
        return false;

      if (! preg_match("/^([a-z]+)$/i", $word))
        return false;

      return true;
    });

    foreach ($words as $word) {
      $counter = 1;

      if (file_exists(__DIR__ . "/corpus/{$type}/{$word}")) {
        // increment counter
        $counter += (int) file_get_contents(__DIR__ . "/corpus/{$type}/{$word}");
      }

      file_put_contents(__DIR__ . "/corpus/{$type}/{$word}", (string) $counter);
    }

    rename(
      $fileInfo->getPathname(),
      str_replace(
        '/learn/',
        '/learned/',
        $fileInfo->getPathname()
      )
    );
  }
}

// decide what to do with stdin

// count documents
$data = array(
  'documents' => 0,
  'words' => 0
);

$director = new RecursiveDirectoryIterator(__DIR__ . "/corpus");
$iterator = new RecursiveIteratorIterator($director, RecursiveIteratorIterator::SELF_FIRST);

foreach ($iterator as $fileInfo) {
  if (! $fileInfo->isFile()) continue;

  $data['words']++;
}

foreach (array('spam', 'ham') as $type) {
  $data[$type]['total'] = 0;
  $data[$type]['score'] = 0;

  $director = new RecursiveDirectoryIterator(__DIR__ . "/learned/{$type}");
  $iterator = new RecursiveIteratorIterator($director, RecursiveIteratorIterator::SELF_FIRST);

  foreach ($iterator as $fileInfo) {
    if (! $fileInfo->isFile()) continue;

    $data[$type]['total']++;
    $data['documents']++;
  }
}

foreach (array('spam', 'ham') as $type) {
  $data[$type]['score'] = log($data[$type]['total'] / $data['documents']);
}

$words = preg_split(
  '/\s+/',
  strtolower(
    file_get_contents(
      'php://stdin'
    )
  )
);

$words = array_filter($words, function ($word) use ($minimum_length, $useless_words) {
  if (strlen($word) < $minimum_length)
    return false;

  if (in_array($word, $useless_words))
    return false;

  if (! preg_match("/^([a-z]+)$/i", $word))
    return false;

  return true;
});

foreach ($words as $word) {
  foreach (array('spam', 'ham') as $type) {
    $count = 0;
    if (file_exists(__DIR__ . "/corpus/{$type}/{$word}")) {
      $count += (int) file_get_contents(__DIR__ . "/corpus/{$type}/{$word}");
    }

    $data[$type]['score'] += log(($count + 1) / ($data[$type]['total'] + $data['words']));
  }
}

if ($data['ham']['score'] >= $data['spam']['score'])
  return print "pass";

return print "fail";
