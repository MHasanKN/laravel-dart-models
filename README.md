# Laravel Dart Models

This package allows you to generate Dart/Flutter models directly from Laravel migrations or database schema. It simplifies the creation of strongly-typed Dart models by parsing your Laravel structure and generating code accordingly.

## Features
- Generate models from Laravel **migrations**.
- Generate models from your **database schema**.
- Supports nullable fields and various Laravel column types.
- Provides JSON serialization methods for Dart models.

## Installation
Add this package to your Laravel project via Composer:

```bash
composer require mhasankn/dart-models
```

## Usage
1. Generate models from migrations:
bash
Copy code
php artisan dart:models --from-migrations
This command will parse all migration files in your Laravel project and generate Dart models based on the table structure.

2. Generate models from the database schema:
bash
Copy code
php artisan dart:models --from-database
This command connects to your Laravel database and generates Dart models based on the existing table schema.

## Example Dart Model
Below is an example of a Dart model generated by the package:

```
class User {
  final String name;
  final String email;
  final DateTime? createdAt;

  User({required this.name, required this.email, this.createdAt});

  factory User.fromJson(Map<String, dynamic> json) => User(
    name: json['name'] as String,
    email: json['email'] as String,
    createdAt: json['createdAt'] != null 
        ? DateTime.parse(json['createdAt']) 
        : null,
  );

  Map<String, dynamic> toJson() => {
    'name': name,
    'email': email,
    'createdAt': createdAt?.toIso8601String(),
  };
}
```

This formatting ensures clarity and consistency for the users of your package. Let me know if further adjustments are needed!

## Contributing
Feel free to open issues or submit PRs. Contributions are welcome!

## License
This package is licensed under the MIT License. See [LICENSE.md](LICENSE.md) for details.
