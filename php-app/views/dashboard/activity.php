<div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
    <div class="md:grid md:grid-cols-3 md:gap-6">
        <div class="md:col-span-1">
            <div class="px-4 sm:px-0">
                <h3 class="text-lg font-medium leading-6 text-gray-900">Activity Log</h3>
                <p class="mt-1 text-sm text-gray-600">
                    View your recent account activity.
                </p>
            </div>

            <!-- Sidebar Navigation -->
            <nav class="mt-5 space-y-1">
                <a href="/dashboard/general" class="border-transparent text-gray-600 hover:bg-gray-50 hover:text-gray-900 block pl-3 pr-4 py-2 border-l-4 text-sm font-medium">
                    General
                </a>
                <a href="/dashboard/security" class="border-transparent text-gray-600 hover:bg-gray-50 hover:text-gray-900 block pl-3 pr-4 py-2 border-l-4 text-sm font-medium">
                    Security
                </a>
                <a href="/dashboard/activity" class="bg-orange-50 border-orange-500 text-orange-700 block pl-3 pr-4 py-2 border-l-4 text-sm font-medium">
                    Activity
                </a>
            </nav>
        </div>

        <div class="mt-5 md:mt-0 md:col-span-2">
            <div class="bg-white shadow overflow-hidden sm:rounded-md">
                <?php if (empty($logs)): ?>
                <div class="px-4 py-5 sm:p-6 text-center text-gray-500">
                    No activity recorded yet.
                </div>
                <?php else: ?>
                <ul class="divide-y divide-gray-200">
                    <?php foreach ($logs as $log): ?>
                    <li>
                        <div class="px-4 py-4 sm:px-6">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-gray-900 truncate">
                                    <?= View::e(ActivityLog::getActionLabel($log['action'])) ?>
                                </p>
                                <div class="ml-2 flex-shrink-0 flex">
                                    <p class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        <?= View::e($log['action']) ?>
                                    </p>
                                </div>
                            </div>
                            <div class="mt-2 sm:flex sm:justify-between">
                                <div class="sm:flex">
                                    <?php if ($log['ip_address']): ?>
                                    <p class="flex items-center text-sm text-gray-500">
                                        <svg class="flex-shrink-0 mr-1.5 h-4 w-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                                        </svg>
                                        <?= View::e($log['ip_address']) ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-2 flex items-center text-sm text-gray-500 sm:mt-0">
                                    <svg class="flex-shrink-0 mr-1.5 h-4 w-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                                    </svg>
                                    <p>
                                        <?= date('M j, Y g:i A', strtotime($log['timestamp'])) ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
