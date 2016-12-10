CREATE TYPE language AS ENUM('en', 'it', 'fr', 'de', 'ru', 'fa', 'hi');

CREATE TABLE "User" (
  chat_id int,
  language language DEFAULT 'en',

  /* Channel id to store the bookmarks */
  channel_id int,

  PRIMARY KEY (chat_id)
);

CREATE TABLE Tag (

    id SERIAL,

    /* Hashtag name without hash */
    name VARCHAR(32) UNIQUE,

    PRIMARY KEY (id)

);

CREATE TABLE Bookmark (

  id SERIAL,

  /* Name of bookmark */
  name text,

  /* Description */
  description text,

  /* Url without 'https://' */
  url text,

  /* Chat_id of the owner */
  user_id int NOT NULL,

  /* Message id of the bookmark in the channel */
  message_id int,

  PRIMARY KEY (id),

  /* References the user_id to the user table */
  FOREIGN KEY (user_id) REFERENCES "User" (chat_id)

);

CREATE TABLE Bookmark_Tag (

    /* Id of the bookmark */
    bookmark_id int NOT NULL,

    /* Id of the tag */
    tag_id int NOT NULL,

    /* Primary keys are both ids */
    PRIMARY KEY (Bookmark_id, Tag_id),

    /* References the bookmark to the bookmark table */
    FOREIGN KEY (Bookmark_id) REFERENCES Bookmark (id),

    /* And the tag to the tag table */
    FOREIGN KEY (Tag_id) REFERENCES Tag (id)
);
