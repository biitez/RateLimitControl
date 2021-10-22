<?php

class RateLimit {    

    /*
        A functional and simple rate limit control to prevent request attacks ready-to-use for PHP.
        Made by https://github.com/biitez 
    */

    # El nombre de este controlador
    private $Controller;

    # Los maximos intentos por hora en cada controlador
    private $MAX_ATTEMPS_PER_HOUR = 10;    

    # El token que identificara el rate limiting
    private $_UserToken;

    # La conexion a la base de datos en PDO (ya debe de venir conectada con exito)
    private $_pdo;

    # Tiempo de limitacion que tendra el usuario en MINUTOS
    private $LIMITATION_TIME;

    function __construct($Controller, $UniqIdenfier, $MaxAttempsPerHour, $LimitationTimeOnMinutes, $pdo)
    {
        # Se crea el controlador
        $this->Controller = $Controller;

        # Los intentos maximos por hora
        $this->MAX_ATTEMPS_PER_HOUR = $MaxAttempsPerHour;

        # Tiempo de limitacion (EN MINUTOS) - E.g. 15 = 15 Minutes
        $this->LIMITATION_TIME = $LimitationTimeOnMinutes;

        # Se le añade el parametro del constructor a una variable interna
        $this->_UserToken = $UniqIdenfier;

        # Se le añade el parametro de la base de datos del constructor a una variable interna
        $this->_pdo = $pdo;
    }

    public function CheckLimit() {

        # Se verifica si el usuario ya esta limitado
        $UserAlreadyLimited = $this->_pdo->prepare('SELECT COUNT(*) FROM internal_rate_limiteds WHERE token = ? AND controller = ?');

        if (!$UserAlreadyLimited->execute([$this->_UserToken, $this->Controller])) {
            throw new PDOException();
        }

        $SeleccionaCantidad = $UserAlreadyLimited->fetchColumn();

        # Si el usuario ya se encuentra limitado
        if ((int)$SeleccionaCantidad > 0) {

            # Se limpian los antiguos LOGS
            $this->LimpiarLasLimitacionesDeLaColeccion($this->Controller);

            # Se selecciona la fecha que deberia de terminar su limitacion (15 Minutos despues de ser limitado)
            $SeleccionarDateTimeLimited = $this->_pdo->prepare(
                'SELECT date_time_passed FROM internal_rate_limiteds WHERE token = ? AND controller = ?');

            if (!$SeleccionarDateTimeLimited->execute([$this->_UserToken, $this->Controller])) {
                throw new PDOException();
            }
                        
            $FechaLimitadaDateTime = $SeleccionarDateTimeLimited->fetchColumn();            
            $FechaActual = date("Y-m-d H:i:s");  

            # Si la fecha actual es MAYOR a la fecha limitada, es decir, 
            # ya llego a la fecha que deberia de terminar la limitacion.
            if ($FechaLimitadaDateTime <= $FechaActual) {

                # Se remueva la fila de la tabla donde decia de la limitacion

                $EliminarLimitacionDeLaDatabase = $this->_pdo->prepare('DELETE FROM internal_rate_limiteds WHERE token = ? AND controller = ?');

                if (!$EliminarLimitacionDeLaDatabase->execute([$this->_UserToken, $this->Controller])) {
                    throw new PDOException();
                }

                # Retorna una booleana verdadera haciendo entender que se puede seguir con la comprobacion
                return true;

            } else { # En caso que no sea asi

                # Convierte la fecha actual a DateTime
                $DFechaActualDelServidor = new DateTime($FechaActual);
                
                # Convierte la fecha que terminara la limitacion a DateTime
                $DFechaQueTerminaraLaLimitadaDelUsuario = new DateTime($FechaLimitadaDateTime);

                # Obtiene la diferencia entre esas 2 fechas
                $DiferenciaEntreLasFechas = $DFechaActualDelServidor->diff($DFechaQueTerminaraLaLimitadaDelUsuario);

                # La retorna
                return $DiferenciaEntreLasFechas;
            }            
        }

        # Si el usuario NO esta limitado;
        # Se interta el token o id unico del usuario a la base de datos junto a la fecha actual
        $InsertRateLimitingValue = $this->_pdo->prepare('INSERT INTO internal_rate_limiting (token,controller,date_time_passing) VALUES (?,?,?)');
        
        $FechaActualDateTime = date("Y-m-d H:i:s");

        if (!$InsertRateLimitingValue->execute([$this->_UserToken, $this->Controller, $FechaActualDateTime])) {
            throw new PDOException();
        }

        # Obtiene la lista de los LOGS de intento del usuario bajo ese controlador
        $ObtenerLosUltimosIntentosPorHora = $this->_pdo->prepare(
            'SELECT * FROM internal_rate_limiting WHERE token = ? AND controller = ? ORDER BY id DESC');
        
        if (!$ObtenerLosUltimosIntentosPorHora->execute([$this->_UserToken, $this->Controller])) {
            throw new PDOException();
        }
        
        $IntentosEnLaHora = 0;

        # Itera cada fila de las LOGS de la limitacion del usuario
        while ($FilaActual = $ObtenerLosUltimosIntentosPorHora->fetch()) {

            # A la fecha mostrada en la base de datos (la fecha que ocurrio la insertacion) se le suma 1 HORA
            $FechaSumando1Hora = date("Y-m-d H:i:s",strtotime($FilaActual['date_time_passing']."+ 1 hour"));
            
            # Se obtiene la fecha actual
            $FechaActualAComprobar = date("Y-m-d H:i:s");

            # Si la fecha actual es MAYOR a la fecha que ocurrio el LOG anterior.
            if ($FechaSumando1Hora < $FechaActualAComprobar) {

                # Se eliminan las logs fuera de la hora
                $EliminarLogs = $this->_pdo->prepare('DELETE FROM internal_rate_limiting WHERE id = ?');
                $EliminarLogs->execute([$FilaActual['id']]);   

            } else {                             

                # Se suma el intento que ocurrio
                $IntentosEnLaHora++;
            }
            
        }

        # Si los intentos dentro de esa hora es mayor a la cantidad permitida (15 por hora)
        if ($IntentosEnLaHora >= $this->MAX_ATTEMPS_PER_HOUR) {

            # Obtiene la fecha actual
            $FechaActualADatabase = date("Y-m-d H:i:s");

            # Le suma 15 Minutos a la fecha actual (sera agregada a la base de datos)
            $FechaActualMas15Minutos = date("Y-m-d H:i:s",strtotime($FechaActualADatabase."+ " . $this->LIMITATION_TIME . " minutes"));

            # Se añade la limitacion del usuario despues de haber superado la cantidad maxima a esta coleccion
            $AñadirLimitacion = $this->_pdo->prepare('INSERT INTO internal_rate_limiteds (token, controller, date_time_limited, date_time_passed) VALUES (?,?,?,?)');
            $AñadirLimitacion->execute([$this->_UserToken, $this->Controller, $FechaActualADatabase, $FechaActualMas15Minutos]);

            # Convierte la fecha actual a DateTime
            $Fecha1ADateTime = new DateTime($FechaActualADatabase);

            # Convierte la fecha actual que se le agrego 15 minutos
            $Fecha2ADateTime = new DateTime($FechaActualMas15Minutos);

            # Se crea la diferencia entre las 2
            $FechaIntervalo = $Fecha1ADateTime->diff($Fecha2ADateTime);

            # Se limpian los LOGs que hayan (y asi no tener basura en la base de datos)
            $this->LimpiarLasLimitacionesDeLaColeccion($this->Controller);

            # Lo retorna
            return $FechaIntervalo;
        }       

        # Si aun no llega a la cantidad maxima de solicitudes, retorna una booleana verdadera para que se continue con el proceso
        return true;
    }

    # Se limpian los LOGs bajo el token/id/ip del usuario y un controlador determinado
    function LimpiarLasLimitacionesDeLaColeccion($Coleccion) {
        $ClearLogs = $this->_pdo->prepare('DELETE FROM internal_rate_limiting WHERE token = ? AND controller = ?');
        if (!$ClearLogs->execute([$this->_UserToken, $Coleccion])) {
            throw new PDOException();
        }
    }     
}