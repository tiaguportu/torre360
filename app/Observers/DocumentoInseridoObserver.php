<?php

namespace App\Observers;

use App\Models\DocumentoInserido;

class DocumentoInseridoObserver
{
    /**
     * Handle the DocumentoInserido "created" event.
     */
    public function created(DocumentoInserido $documentoInserido): void
    {
        //
    }

    /**
     * Handle the DocumentoInserido "updated" event.
     */
    public function updated(DocumentoInserido $documentoInserido): void
    {
        //
    }

    /**
     * Handle the DocumentoInserido "deleted" event.
     */
    public function deleted(DocumentoInserido $documentoInserido): void
    {
        //
    }

    /**
     * Handle the DocumentoInserido "restored" event.
     */
    public function restored(DocumentoInserido $documentoInserido): void
    {
        //
    }

    /**
     * Handle the DocumentoInserido "force deleted" event.
     */
    public function forceDeleted(DocumentoInserido $documentoInserido): void
    {
        //
    }
}
