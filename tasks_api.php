<?php
// Database connection parameters - update as needed
define('DB_HOST', 'database-1.czhqziufcaib.us-west-2.rds.amazonaws.com');
define('DB_NAME', 'task_logger');
define('DB_USER', 'admin');
define('DB_PSWD', 'AXXRWZ90SSJ23s3DLrRq');

try {
  
  // Connect to/validate database connection constants above
  $mysqli = new mysqli(DB_HOST, DB_USER, DB_PSWD, DB_NAME);
  if ($mysqli->connect_errno) {
    throw new Exception('Unable to connect to database');
  }
  
  // Validate schema
  if (!$mysqli->query('SELECT id, title, created FROM tasks LIMIT 1')) {
    throw new Exception('tasks table does not exist');
  }
  
  // Create new task or update existing
  $id = NULL;
  if ($_SERVER['REQUEST_METHOD'] == 'POST' || $_SERVER['REQUEST_METHOD'] == 'PUT') {
    // Updating existing task
    if (preg_match('/^([0-9]+)\/(.*)$/', urldecode($_SERVER['QUERY_STRING']), $m)) {
      $id = $m[1];
      $title = $m[2];
    }
    // Creating new task
    else {
      $id = NULL;
      $title = urldecode($_SERVER['QUERY_STRING']);
    }
    // Title must be between 6-255 characters
    if (strlen($title) < 6 || strlen($title) > 255) {
      header('HTTP/1.1 400 Bad Request');
      printf("%s\n", 'title must be between 6 and 255 characters');
      exit;
    }
    // Update existing
    $escaped_title = $mysqli->real_escape_string($title);
    if ($id) {
      $query = sprintf("UPDATE tasks SET title='%s' WHERE id=%d", $escaped_title, $id);
    }
    else {
      $query = sprintf("INSERT INTO tasks (title) VALUES ('%s')", $escaped_title);
    }
    if (!$mysqli->query($query)) {
      throw new Exception(sprintf('Unable to invoke query: %s', $query));
    }
    
    // Invalid ID
    if ($id && !$mysqli->affected_rows) {
      header('HTTP/1.1 404 Not Found');
      exit;
    }
    else if (!$id) {
      if ($id = $mysqli->insert_id) {
        header('HTTP/1.1 201 Created');
      }
      else {
        throw new Exception('Unable to determine ID');
      }
    }
  }
  // Return task object(s)
  if ($_SERVER['REQUEST_METHOD'] == 'POST' || 
      $_SERVER['REQUEST_METHOD'] == 'PUT' || 
      $_SERVER['REQUEST_METHOD'] == 'GET') {
    if (!$id && preg_match('/^([0-9]+)\/?/', urldecode($_SERVER['QUERY_STRING']), $m)) {
      $id = $m[1];
    }
    $query = sprintf('SELECT id, title, created FROM tasks ');
    if ($id) $query .= sprintf('WHERE id=%s ', $id);
    $query .= 'ORDER BY created';
    $results = $mysqli->query($query);
    if (!$results) {
      throw new Exception(sprintf('Unable to invoke query: %s', $query));
    }
    // Invalid ID
    else if ($id && !$results->num_rows) {
      header('HTTP/1.1 404 Not Found');
      exit;
    }
    $tasks = array();
    while($task = mysqli_fetch_assoc($results)) $tasks[] = $task;
    header('Content-Type: application/json');
    printf("%s\n", json_encode($id ? $tasks[0] : $tasks));
  }
  // Delete a task object
  else if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
    if (preg_match('/^([0-9]+)\/?/', urldecode($_SERVER['QUERY_STRING']), $m)) {
      $id = $m[1];
      $query = sprintf('DELETE FROM tasks WHERE id=%s', $id);
      if (!$mysqli->query($query)) {
        throw new Exception(sprintf('Unable to invoke query: %s', $query));
      }
      // Invalid ID
      if ($id && !$mysqli->affected_rows) {
        header('HTTP/1.1 404 Not Found');
        exit;
      }
      print("\n");
    }
    else {
      header('HTTP/1.1 400 Bad Request');
      printf("%s\n", 'id is required');
      exit;
    }
  }
  
  $mysqli->close();
}
catch(Exception $e) {
  header('HTTP/1.1 500 Internal Server Error');
  printf("An error has occurred\n%s\n", $e->getMessage());
}
?>
