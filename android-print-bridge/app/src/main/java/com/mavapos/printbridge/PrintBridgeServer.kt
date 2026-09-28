package com.mavapos.printbridge

import android.Manifest
import android.bluetooth.BluetoothAdapter
import android.bluetooth.BluetoothDevice
import android.bluetooth.BluetoothSocket
import android.content.Context
import android.content.pm.PackageManager
import android.os.Build
import org.json.JSONArray
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.io.OutputStream
import java.net.ServerSocket
import java.util.UUID
import java.util.concurrent.Executors

class PrintBridgeServer(private val context: Context, private val onStatus: (String) -> Unit) {
    private val executor = Executors.newCachedThreadPool()
    private var server: ServerSocket? = null
    private var socket: BluetoothSocket? = null
    private var lastAddress: String? = context.getSharedPreferences("bridge", Context.MODE_PRIVATE).getString("printer_address", null)
    private val sppUuid = UUID.fromString("00001101-0000-1000-8000-00805F9B34FB")

    fun start() { executor.execute { server = ServerSocket(8765).also { onStatus("HTTP bridge aktif di 127.0.0.1:8765"); while (!it.isClosed) executor.execute { handle(it.accept()) } } } }
    fun stop() { server?.close(); socket?.close(); executor.shutdownNow() }

    private fun handle(client: java.net.Socket) {
        client.use { val reader = BufferedReader(InputStreamReader(it.getInputStream())); val requestLine = reader.readLine() ?: return
            var length = 0
            while (true) { val line = reader.readLine() ?: break; if (line.isEmpty()) break; if (line.startsWith("Content-Length:", true)) length = line.substringAfter(':').trim().toIntOrNull() ?: 0 }
            val body = CharArray(length); reader.read(body); val path = requestLine.split(" ").getOrNull(1) ?: "/"; val response = route(path, String(body));
            val bytes = response.toByteArray(); val out = it.getOutputStream(); out.write("HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nAccess-Control-Allow-Origin: *\r\nContent-Length: ${bytes.size}\r\nConnection: close\r\n\r\n".toByteArray()); out.write(bytes); out.flush()
        }
    }

    private fun route(path: String, body: String): String = try {
        when {
            path == "/health" -> JSONObject().put("ok", true).put("connected", socket?.isConnected == true).put("printerAddress", lastAddress ?: JSONObject.NULL).put("autoPrint", autoPrint()).toString()
            path == "/printers" -> printers().toString()
            path == "/printers/connect" -> { val address = JSONObject(body).getString("address"); connect(address); JSONObject().put("ok", true).put("address", address).toString() }
            path == "/settings/auto-print" -> { val enabled = JSONObject(body).optBoolean("enabled", false); context.getSharedPreferences("bridge", Context.MODE_PRIVATE).edit().putBoolean("auto_print", enabled).apply(); JSONObject().put("ok", true).put("enabled", enabled).toString() }
            path == "/print" -> { val data = android.util.Base64.decode(JSONObject(body).getString("cpclBase64"), android.util.Base64.DEFAULT); print(data); JSONObject().put("ok", true).toString() }
            else -> JSONObject().put("ok", false).put("error", "Unknown endpoint").toString()
        }
    } catch (e: Exception) { JSONObject().put("ok", false).put("error", e.message ?: "Bridge error").toString() }

    private fun printers(): JSONArray { val result = JSONArray(); if (Build.VERSION.SDK_INT >= 31 && context.checkSelfPermission(Manifest.permission.BLUETOOTH_CONNECT) != PackageManager.PERMISSION_GRANTED) return result; BluetoothAdapter.getDefaultAdapter()?.bondedDevices?.forEach { result.put(JSONObject().put("name", it.name ?: "Unknown").put("address", it.address)) }; return result }
    private fun connect(address: String) { if (Build.VERSION.SDK_INT >= 31 && context.checkSelfPermission(Manifest.permission.BLUETOOTH_CONNECT) != PackageManager.PERMISSION_GRANTED) error("Bluetooth permission not granted"); val device: BluetoothDevice = BluetoothAdapter.getDefaultAdapter().getRemoteDevice(address); socket?.close(); socket = device.createRfcommSocketToServiceRecord(sppUuid).also { it.connect() }; lastAddress = address; context.getSharedPreferences("bridge", Context.MODE_PRIVATE).edit().putString("printer_address", address).apply(); onStatus("Terhubung ke ${device.name ?: address}") }
    private fun autoPrint(): Boolean = context.getSharedPreferences("bridge", Context.MODE_PRIVATE).getBoolean("auto_print", true)
    private fun print(data: ByteArray) { if (socket?.isConnected != true) lastAddress?.let { connect(it) } ?: error("Printer belum terhubung"); socket!!.outputStream.useOutput(data) }
    private fun OutputStream.useOutput(data: ByteArray) { write(data); flush() }
}
