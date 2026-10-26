package com.japacoach.japa_coach

import android.app.NotificationChannel
import android.app.NotificationManager
import android.os.Build
import android.os.Bundle
import androidx.activity.enableEdgeToEdge
import io.flutter.embedding.android.FlutterFragmentActivity

// FlutterFragmentActivity (an AndroidX ComponentActivity) so enableEdgeToEdge() is available.
class MainActivity : FlutterFragmentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        // Draw behind the status and navigation bars on every Android version, as Android 15+ does by
        // default. Flutter's SafeArea, AppBar and NavigationBar keep content clear of the bars.
        enableEdgeToEdge()
        super.onCreate(savedInstanceState)
        createNotificationChannel()
    }

    /** The channel the server sends pushes to ("japa_default"); users can mute it in system settings. */
    private fun createNotificationChannel() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        val channel = NotificationChannel("japa_default", "Results and reminders", NotificationManager.IMPORTANCE_HIGH).apply {
            description = "Your test results, study reminders and plan updates"
        }
        getSystemService(NotificationManager::class.java).createNotificationChannel(channel)
    }
}
