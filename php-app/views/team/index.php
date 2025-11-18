<div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
    <div class="px-4 py-6 sm:px-0">
        <h1 class="text-2xl font-semibold text-gray-900">Team Settings</h1>
        <p class="mt-1 text-sm text-gray-600">Manage your team members and invitations.</p>

        <!-- Team Members -->
        <div class="mt-6">
            <h2 class="text-lg font-medium text-gray-900">Team Members</h2>
            <div class="mt-4 bg-white shadow overflow-hidden sm:rounded-md">
                <ul class="divide-y divide-gray-200">
                    <?php foreach ($team['members'] as $member): ?>
                    <li>
                        <div class="px-4 py-4 flex items-center justify-between sm:px-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                        <span class="text-sm font-medium text-gray-600">
                                            <?= strtoupper(substr($member['name'] ?? $member['email'], 0, 2)) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-900">
                                        <?= View::e($member['name'] ?? $member['email']) ?>
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        <?= View::e($member['email']) ?>
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-4">
                                <span class="px-2 py-1 text-xs font-medium rounded-full <?= $member['role'] === 'owner' ? 'bg-orange-100 text-orange-800' : 'bg-gray-100 text-gray-800' ?>">
                                    <?= ucfirst(View::e($member['role'])) ?>
                                </span>
                                <?php if ($member['user_id'] !== $user['id']): ?>
                                <form action="/dashboard/team/remove-member" method="POST" onsubmit="return confirm('Remove this team member?');">
                                    <?= View::csrf() ?>
                                    <input type="hidden" name="memberId" value="<?= $member['id'] ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-900 text-sm">
                                        Remove
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Invite Member -->
        <div class="mt-8">
            <h2 class="text-lg font-medium text-gray-900">Invite Team Member</h2>
            <form action="/dashboard/team/invite" method="POST" class="mt-4">
                <?= View::csrf() ?>
                <div class="bg-white shadow sm:rounded-md">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div class="sm:col-span-2">
                                <label for="email" class="block text-sm font-medium text-gray-700">
                                    Email address
                                </label>
                                <input
                                    type="email"
                                    name="email"
                                    id="email"
                                    class="mt-1 focus:ring-orange-500 focus:border-orange-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                    placeholder="teammate@example.com"
                                    required
                                >
                            </div>
                            <div>
                                <label for="role" class="block text-sm font-medium text-gray-700">
                                    Role
                                </label>
                                <select
                                    name="role"
                                    id="role"
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-orange-500 focus:border-orange-500 sm:text-sm"
                                >
                                    <option value="member">Member</option>
                                    <option value="owner">Owner</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                        <button
                            type="submit"
                            class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500"
                        >
                            Send Invitation
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Pending Invitations -->
        <?php if (!empty($invitations)): ?>
        <div class="mt-8">
            <h2 class="text-lg font-medium text-gray-900">Pending Invitations</h2>
            <div class="mt-4 bg-white shadow overflow-hidden sm:rounded-md">
                <ul class="divide-y divide-gray-200">
                    <?php foreach ($invitations as $invitation): ?>
                    <?php if ($invitation['status'] === 'pending'): ?>
                    <li>
                        <div class="px-4 py-4 flex items-center justify-between sm:px-6">
                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    <?= View::e($invitation['email']) ?>
                                </p>
                                <p class="text-sm text-gray-500">
                                    Invited as <?= View::e($invitation['role']) ?> on <?= date('M j, Y', strtotime($invitation['invited_at'])) ?>
                                </p>
                            </div>
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800">
                                Pending
                            </span>
                        </div>
                    </li>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <!-- Subscription Info -->
        <?php if ($team['subscription_status']): ?>
        <div class="mt-8">
            <h2 class="text-lg font-medium text-gray-900">Subscription</h2>
            <div class="mt-4 bg-white shadow sm:rounded-md">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-sm font-medium text-gray-900">
                                Current Plan: <?= View::e($team['plan_name'] ?? 'Free') ?>
                            </p>
                            <p class="text-sm text-gray-500">
                                Status: <?= ucfirst(View::e($team['subscription_status'])) ?>
                            </p>
                        </div>
                        <a href="/pricing" class="text-orange-600 hover:text-orange-500 text-sm font-medium">
                            Manage subscription
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
