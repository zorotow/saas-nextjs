<div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
    <div class="text-center">
        <h2 class="text-3xl font-extrabold text-gray-900 sm:text-4xl">
            Simple, transparent pricing
        </h2>
        <p class="mt-4 text-xl text-gray-600">
            Choose the plan that's right for you
        </p>
    </div>

    <div class="mt-12 space-y-4 sm:mt-16 sm:space-y-0 sm:grid sm:grid-cols-2 sm:gap-6 lg:max-w-4xl lg:mx-auto xl:max-w-none xl:grid-cols-3">
        <!-- Free Plan -->
        <div class="border border-gray-200 rounded-lg shadow-sm divide-y divide-gray-200 bg-white">
            <div class="p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Free</h3>
                <p class="mt-4 text-sm text-gray-500">Perfect for getting started</p>
                <p class="mt-8">
                    <span class="text-4xl font-extrabold text-gray-900">$0</span>
                    <span class="text-base font-medium text-gray-500">/mo</span>
                </p>
                <a href="/sign-up" class="mt-8 block w-full bg-gray-100 border border-gray-300 rounded-md py-2 text-sm font-semibold text-gray-900 text-center hover:bg-gray-200">
                    Get started
                </a>
            </div>
            <div class="pt-6 pb-8 px-6">
                <h4 class="text-xs font-medium text-gray-900 tracking-wide uppercase">What's included</h4>
                <ul class="mt-6 space-y-4">
                    <li class="flex space-x-3">
                        <svg class="flex-shrink-0 h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span class="text-sm text-gray-500">1 team member</span>
                    </li>
                    <li class="flex space-x-3">
                        <svg class="flex-shrink-0 h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span class="text-sm text-gray-500">Basic features</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Base Plan -->
        <div class="border border-orange-500 rounded-lg shadow-sm divide-y divide-gray-200 bg-white">
            <div class="p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Base</h3>
                <p class="mt-4 text-sm text-gray-500">For small teams</p>
                <p class="mt-8">
                    <span class="text-4xl font-extrabold text-gray-900">$8</span>
                    <span class="text-base font-medium text-gray-500">/mo</span>
                </p>
                <?php if (isset($currentUser) && $currentUser): ?>
                <form action="/api/stripe/checkout" method="POST">
                    <?= View::csrf() ?>
                    <input type="hidden" name="priceId" value="price_base">
                    <button type="submit" class="mt-8 block w-full bg-orange-600 border border-transparent rounded-md py-2 text-sm font-semibold text-white text-center hover:bg-orange-700">
                        Subscribe
                    </button>
                </form>
                <?php else: ?>
                <a href="/sign-up?redirect=checkout&priceId=price_base" class="mt-8 block w-full bg-orange-600 border border-transparent rounded-md py-2 text-sm font-semibold text-white text-center hover:bg-orange-700">
                    Subscribe
                </a>
                <?php endif; ?>
            </div>
            <div class="pt-6 pb-8 px-6">
                <h4 class="text-xs font-medium text-gray-900 tracking-wide uppercase">What's included</h4>
                <ul class="mt-6 space-y-4">
                    <li class="flex space-x-3">
                        <svg class="flex-shrink-0 h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span class="text-sm text-gray-500">Up to 5 team members</span>
                    </li>
                    <li class="flex space-x-3">
                        <svg class="flex-shrink-0 h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span class="text-sm text-gray-500">All basic features</span>
                    </li>
                    <li class="flex space-x-3">
                        <svg class="flex-shrink-0 h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span class="text-sm text-gray-500">Priority support</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Plus Plan -->
        <div class="border border-gray-200 rounded-lg shadow-sm divide-y divide-gray-200 bg-white">
            <div class="p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Plus</h3>
                <p class="mt-4 text-sm text-gray-500">For growing teams</p>
                <p class="mt-8">
                    <span class="text-4xl font-extrabold text-gray-900">$12</span>
                    <span class="text-base font-medium text-gray-500">/mo</span>
                </p>
                <?php if (isset($currentUser) && $currentUser): ?>
                <form action="/api/stripe/checkout" method="POST">
                    <?= View::csrf() ?>
                    <input type="hidden" name="priceId" value="price_plus">
                    <button type="submit" class="mt-8 block w-full bg-gray-800 border border-transparent rounded-md py-2 text-sm font-semibold text-white text-center hover:bg-gray-900">
                        Subscribe
                    </button>
                </form>
                <?php else: ?>
                <a href="/sign-up?redirect=checkout&priceId=price_plus" class="mt-8 block w-full bg-gray-800 border border-transparent rounded-md py-2 text-sm font-semibold text-white text-center hover:bg-gray-900">
                    Subscribe
                </a>
                <?php endif; ?>
            </div>
            <div class="pt-6 pb-8 px-6">
                <h4 class="text-xs font-medium text-gray-900 tracking-wide uppercase">What's included</h4>
                <ul class="mt-6 space-y-4">
                    <li class="flex space-x-3">
                        <svg class="flex-shrink-0 h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span class="text-sm text-gray-500">Unlimited team members</span>
                    </li>
                    <li class="flex space-x-3">
                        <svg class="flex-shrink-0 h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span class="text-sm text-gray-500">All base features</span>
                    </li>
                    <li class="flex space-x-3">
                        <svg class="flex-shrink-0 h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span class="text-sm text-gray-500">Advanced analytics</span>
                    </li>
                    <li class="flex space-x-3">
                        <svg class="flex-shrink-0 h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span class="text-sm text-gray-500">24/7 premium support</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
