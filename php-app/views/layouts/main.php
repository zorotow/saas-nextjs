<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= View::e($title ?? 'SaaS App') ?> - <?= View::e($appName) ?></title>
    <link href="/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    <?php if (isset($currentUser) && $currentUser): ?>
    <!-- Navigation for authenticated users -->
    <nav class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="/dashboard" class="text-xl font-bold text-gray-900">
                        <?= View::e($appName) ?>
                    </a>
                    <div class="hidden md:flex ml-10 space-x-4">
                        <a href="/dashboard" class="text-gray-600 hover:text-gray-900 px-3 py-2 text-sm font-medium">
                            Dashboard
                        </a>
                        <a href="/dashboard/team" class="text-gray-600 hover:text-gray-900 px-3 py-2 text-sm font-medium">
                            Team
                        </a>
                        <a href="/pricing" class="text-gray-600 hover:text-gray-900 px-3 py-2 text-sm font-medium">
                            Pricing
                        </a>
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="relative group">
                        <button class="flex items-center text-sm font-medium text-gray-700 hover:text-gray-900">
                            <?= View::e($currentUser['name'] ?? $currentUser['email']) ?>
                            <svg class="ml-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                        <div class="hidden group-hover:block absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-10">
                            <a href="/dashboard/general" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                Settings
                            </a>
                            <a href="/dashboard/activity" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                Activity
                            </a>
                            <hr class="my-1">
                            <form action="/sign-out" method="POST" class="block">
                                <?= View::csrf() ?>
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    Sign Out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    <?php endif; ?>

    <!-- Flash messages -->
    <?php if ($error = View::flash('error')): ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
            <?= View::e($error) ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($success = View::flash('success')): ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
            <?= View::e($success) ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main content -->
    <main>
        <?= $content ?>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t mt-auto">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <p class="text-center text-sm text-gray-500">
                &copy; <?= date('Y') ?> <?= View::e($appName) ?>. All rights reserved.
            </p>
        </div>
    </footer>

    <script src="/js/app.js"></script>
</body>
</html>
