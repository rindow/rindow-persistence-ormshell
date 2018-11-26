ORM persisitence service Shell
==============================

Master: [![Build Status](https://travis-ci.com/rindow/rindow-persistence-ormshell.png?branch=master)](https://travis-ci.com/rindow/rindow-persistence-ormshell)

This module supports developers implementing the Java Persisitence API style ORM service.

It is useful when creating an alternative product if you only want to replace the ORM part in an environment where an existing ORM service can not be used.

This module provides an EntityManager interface used by applications, but no mapper is provided. The ORM service is completed by making the mapper suitable for your environment by the developer.

