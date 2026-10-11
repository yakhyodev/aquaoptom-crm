package com.aquaoptom.crm.mobile

import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import android.util.Base64
import android.app.DownloadManager
import android.content.Context
import android.net.Uri
import android.os.Environment
import java.security.KeyStore
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec

class MainActivity : FlutterActivity() {
    private val alias = "aquaoptom_session_v1"
    private fun key(): SecretKey {
        val store = KeyStore.getInstance("AndroidKeyStore").apply { load(null) }
        if (store.containsAlias(alias)) return store.getKey(alias, null) as SecretKey
        return KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, "AndroidKeyStore").apply {
            init(KeyGenParameterSpec.Builder(alias, KeyProperties.PURPOSE_ENCRYPT or KeyProperties.PURPOSE_DECRYPT)
                .setBlockModes(KeyProperties.BLOCK_MODE_GCM).setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE).build())
        }.generateKey()
    }
    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        val prefs = getSharedPreferences("aquaoptom_private", Context.MODE_PRIVATE)
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "aquaoptom/session").setMethodCallHandler { call, result ->
            try {
                when (call.method) {
                    "write" -> {
                        val cipher = Cipher.getInstance("AES/GCM/NoPadding").apply { init(Cipher.ENCRYPT_MODE, key()) }
                        val encrypted = cipher.doFinal((call.arguments as String).toByteArray(Charsets.UTF_8))
                        check(prefs.edit().putString("session", Base64.encodeToString(encrypted, Base64.NO_WRAP))
                            .putString("iv", Base64.encodeToString(cipher.iv, Base64.NO_WRAP)).commit())
                        result.success(null)
                    }
                    "read" -> {
                        val encrypted = prefs.getString("session", null)
                        val iv = prefs.getString("iv", null)
                        if (encrypted == null || iv == null) result.success(null) else {
                            val cipher = Cipher.getInstance("AES/GCM/NoPadding").apply {
                                init(Cipher.DECRYPT_MODE, key(), GCMParameterSpec(128, Base64.decode(iv, Base64.NO_WRAP)))
                            }
                            result.success(String(cipher.doFinal(Base64.decode(encrypted, Base64.NO_WRAP)), Charsets.UTF_8))
                        }
                    }
                    "clear" -> { check(prefs.edit().remove("session").remove("iv").commit()); result.success(null) }
                    "readTheme" -> result.success(prefs.getString("theme", "system"))
                    "writeTheme" -> { prefs.edit().putString("theme", call.arguments as String).apply(); result.success(null) }
                    "downloadReport" -> {
                        val url = call.argument<String>("url")!!
                        val uri = Uri.parse(url)
                        val origin = Uri.parse(call.argument<String>("api_origin")!!)
                        require(uri.scheme == "https" && uri.host == origin.host && uri.port == origin.port)
                        val filename = (call.argument<String>("file_name") ?: "hisobot.xlsx").replace(Regex("[^a-zA-Z0-9._-]"), "_")
                        val request = DownloadManager.Request(uri).setTitle(filename)
                            .setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED)
                            .setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, filename)
                            .addRequestHeader("Authorization", "Bearer " + call.argument<String>("token"))
                        result.success((getSystemService(Context.DOWNLOAD_SERVICE) as DownloadManager).enqueue(request))
                    }
                    else -> result.notImplemented()
                }
            } catch (e: Exception) {
                result.error("SECURE_STORAGE", "Qurilma amalini bajarib bo‘lmadi", null)
            }
        }
    }
}
