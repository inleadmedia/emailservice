<?php

namespace Drupal\emailservice\Models;

class Item {

  public $identifier;
  public $title;
  public $type;
  public $url;
  public $subject;
  public $creator;
  public $date;
  public $image;
  public $type_key;
  public $subject_key;

  /**
   * @param mixed $identifier
   */
  public function setIdentifier($identifier): void {
    $this->identifier = $identifier;
  }

  /**
   * @param mixed $title
   */
  public function setTitle($title): void {
    $this->title = $title;
  }

  /**
   * @param mixed $type
   */
  public function setType($type): void {
    $this->type = $type;
  }

  /**
   * @param mixed $url
   */
  public function setUrl($url): void {
    $this->url = $url;
  }

  /**
   * @param mixed $subject
   */
  public function setSubject($subject): void {
    $this->subject = $subject;
  }

  /**
   * @param mixed $author
   */
  public function setAuthor($author) {
    $this->creator = $author;
  }

  /**
   * @param mixed $date
   */
  public function setDate($date): void {
    $this->date = $date;
  }

  /**
   * @param mixed $cover
   */
  public function setCover($cover): void {
    $this->image = $cover;
  }

  /**
   * @param mixed $type_key
   */
  public function setTypeKey($type_key): void {
    $this->type_key = strtolower($type_key);
  }

  /**
   * @param mixed $subject_key
   */
  public function setSubjectKey($subject_key): void {
    $this->subject_key = $subject_key;
  }

}
