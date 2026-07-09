package com.mavapos.customerdisplay

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.Service
import android.content.Context
import android.content.Intent
import android.os.Build
import android.os.IBinder
import android.util.Log
import com.imin.lcd.ILcdManager
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL

class CustomerDisplayService : Service() {

    private val scope = CoroutineScope(Dispatchers.IO + Job())
    private var pollingJob: Job? = null
    private var renderer: LcdRenderer? = null
    private var lastSignature: String = ""

    override fun onCreate() {
        super.onCreate()
        startForegroundWithNotification()
        renderer = LcdRenderer(applicationContext).also { it.init() }
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        if (pollingJob?.isActive == true) return START_STICKY
        pollingJob = scope.launch {
            while (true) {
                pollOnce()
                delay(1000)
            }
        }
        return START_STICKY
    }

    override fun onDestroy() {
        pollingJob?.cancel()
        renderer?.shutdown()
        super.onDestroy()
    }

    override fun onBind(intent: Intent?): IBinder? = null

    private fun pollOnce() {
        val url = BuildConfig.POS_DISPLAY_STATE_URL.ifEmpty { return }
        try {
            val conn = (URL(url).openConnection() as HttpURLConnection).apply {
                connectTimeout = 800
                readTimeout = 800
                requestMethod = "GET"
                setRequestProperty("Accept", "application/json")
            }
            val body = conn.inputStream.bufferedReader().use { it.readText() }
            conn.disconnect()

            val json = JSONObject(body)
            val signature = buildSignature(json)
            if (signature == lastSignature) return
            lastSignature = signature
            renderer?.render(json)
        } catch (error: Exception) {
            Log.w(TAG, "poll failed: ${error.message}")
        }
    }

    private fun buildSignature(json: JSONObject): String {
        return buildString {
            append(json.optString("mode"))
            append("|")
            append(json.optInt("total"))
            append("|")
            append(json.optInt("change_amount"))
            append("|")
            append(json.optJSONArray("cart")?.length() ?: 0)
        }
    }

    private fun startForegroundWithNotification() {
        val channelId = "mavapos_display"
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                channelId,
                "Customer Display",
                NotificationManager.IMPORTANCE_LOW,
            )
            getSystemService(NotificationManager::class.java).createNotificationChannel(channel)
        }
        val notification: Notification = Notification.Builder(this, channelId)
            .setContentTitle("MavaPOS Display")
            .setContentText("Menampilkan total di sub-display")
            .setSmallIcon(android.R.drawable.ic_menu_info_details)
            .build()
        startForeground(1, notification)
    }

    companion object {
        private const val TAG = "CustomerDisplay"

        fun start(context: Context) {
            val intent = Intent(context, CustomerDisplayService::class.java)
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                context.startForegroundService(intent)
            } else {
                context.startService(intent)
            }
        }
    }
}
