<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo html_escape($product['name']); ?></title>
</head>
<body>
    <h1><?php echo html_escape($product['name']); ?></h1>
    <dl>
        <dt>Code</dt>
        <dd><?php echo html_escape($product['code']); ?></dd>
        <dt>Category</dt>
        <dd><?php echo html_escape($product['category_name']); ?></dd>
        <dt>Price</dt>
        <dd><?php echo (int) $product['price']; ?></dd>
    </dl>
</body>
</html>
