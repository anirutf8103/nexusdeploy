<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusDeploy - Local FTP Dashboard</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Phosphor Icons -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        dark: {
                            bg: '#0f172a',
                            /* slate-900 */
                            panel: '#1e293b',
                            /* slate-800 */
                            border: '#334155',
                            /* slate-700 */
                        },
                        primary: {
                            DEFAULT: '#3b82f6',
                            /* neon blue */
                            hover: '#60a5fa',
                        },
                        accent: {
                            DEFAULT: '#10b981',
                            /* emerald green */
                        }
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-dark-bg text-gray-200 font-sans antialiased h-screen flex overflow-hidden dark">
    <?php include 'sidebar.php'; ?>
    <div class="flex-1 flex flex-col h-screen overflow-hidden">
        <header class="h-16 bg-dark-panel border-b border-dark-border flex items-center justify-between px-6 z-10 w-full shrink-0">
            <h1 class="text-xl font-semibold tracking-wide text-white" id="pageTitle">
                <?php
                $page = basename($_SERVER['PHP_SELF']);
                if ($page == 'index.php') echo 'Dashboard';
                elseif ($page == 'projects.php') echo 'Projects';
                elseif ($page == 'servers.php') echo 'Servers';
                elseif ($page == 'deploy.php') echo 'Deploy Engine';
                elseif ($page == 'logs.php') echo 'Deployment Logs';
                else echo 'NexusDeploy';
                ?>
            </h1>
            <div class="flex items-center space-x-4">
                <span class="text-sm px-3 py-1 bg-dark-border rounded-full text-gray-300 font-medium flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-accent animate-pulse"></div> Localhost Mode
                </span>
                <div class="w-8 h-8 rounded-md bg-gradient-to-br from-primary to-blue-700 flex items-center justify-center text-white shadow-lg shadow-primary/20">
                    <i class="ph ph-terminal-window text-lg"></i>
                </div>
            </div>
        </header>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-dark-bg p-6 custom-scrollbar relative">