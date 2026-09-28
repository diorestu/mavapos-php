package com.mavapos.printbridge

import android.Manifest
import android.app.Activity
import android.os.Bundle
import android.os.Build
import android.content.pm.PackageManager
import android.graphics.Color
import android.view.Gravity
import android.widget.LinearLayout
import android.widget.TextView

class MainActivity : Activity() {
    private lateinit var status: TextView
    private lateinit var bridge: PrintBridgeServer

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        status = TextView(this).apply { textSize = 16f; setTextColor(Color.DKGRAY); setPadding(32, 32, 32, 32); gravity = Gravity.CENTER_VERTICAL; text = "MavaPOS Print Bridge\nMenunggu koneksi..." }
        setContentView(LinearLayout(this).apply { orientation = LinearLayout.VERTICAL; addView(status) })
        requestBluetoothPermissions()
        bridge = PrintBridgeServer(this) { message -> runOnUiThread { status.text = "MavaPOS Print Bridge\n$message" } }
        bridge.start()
    }

    private fun requestBluetoothPermissions() {
        if (Build.VERSION.SDK_INT >= 31) requestPermissions(arrayOf(Manifest.permission.BLUETOOTH_CONNECT, Manifest.permission.BLUETOOTH_SCAN), 100)
    }

    override fun onDestroy() { bridge.stop(); super.onDestroy() }
}
