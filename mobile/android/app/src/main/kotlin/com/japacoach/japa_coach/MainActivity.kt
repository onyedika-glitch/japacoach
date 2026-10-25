package com.japacoach.japa_coach

import android.app.NotificationChannel
import android.app.NotificationManager
import android.os.Build
import android.os.Bundle
import io.flutter.embedding.android.FlutterActivity

class MainActivity : FlutterActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
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
