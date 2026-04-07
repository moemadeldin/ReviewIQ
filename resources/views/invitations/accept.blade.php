<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Accept Invitation - ReviewIQ</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="font-sans antialiased bg-gray-50 dark:bg-gray-900 min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full p-8 bg-white dark:bg-gray-800 rounded-lg shadow-lg">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100 mb-2">
                    You're invited to join {{ $invitation->workspace->name }}
                </h1>
                <p class="text-gray-600 dark:text-gray-400">
                    {{ $invitation->workspace->description ?? 'A workspace on ReviewIQ' }}
                </p>
            </div>

            <div class="mb-6 p-4 bg-gray-100 dark:bg-gray-700 rounded-lg">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Invited as: <span class="font-medium text-gray-900 dark:text-gray-200">{{ $invitation->role }}</span>
                </p>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Email: <span class="font-medium text-gray-900 dark:text-gray-200">{{ $invitation->email }}</span>
                </p>
            </div>

            @if($isExistingUser)
                <form method="POST" action="{{ route('invitations.accept', ['token' => $invitation->token]) }}">
                    @csrf
                    <button type="submit" class="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors">
                        Accept Invitation
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('invitations.accept', ['token' => $invitation->token]) }}">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Your Name</label>
                            <input type="text" name="name" id="name" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password</label>
                            <input type="password" name="password" id="password" required minlength="8" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm Password</label>
                            <input type="password" name="password_confirmation" id="password_confirmation" required minlength="8" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <button type="submit" class="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors">
                            Create Account & Accept
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </body>
</html>