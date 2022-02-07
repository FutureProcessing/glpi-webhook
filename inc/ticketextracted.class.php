<?php

final class PluginFpwebhookTicketExtracted
{
   /**
    * @var string Title of the ticket
    */
   private string $title;

   /**
    * @var int|null ID of the category of the ticket
    */
   private ?int $category_id;

   /**
    * @param string $title
    * @param int|null $category_id
    */
   public function __construct(string $title, ?int $category_id)
   {
      $this->title = $title;
      $this->category_id = $category_id;
   }

   /**
    * @return string
    */
   public function getTitle(): string
   {
      return $this->title;
   }

   /**
    * @return int|null
    */
   public function getCategoryId(): ?int
   {
      return $this->category_id;
   }
}
